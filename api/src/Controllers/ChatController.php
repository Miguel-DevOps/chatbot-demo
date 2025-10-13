<?php

declare(strict_types=1);

namespace ChatbotDemo\Controllers;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Config\OpenTelemetryBootstrap;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\RateLimitService;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;
use RuntimeException;

/**
 * Chat Controller
 * Handles HTTP requests related to chat
 */
class ChatController
{
    private ChatService $chatService;
    private RateLimitService $rateLimitService;
    private AppConfig $config;
    private LoggerInterface $logger;
    private TracerInterface $tracer;

    public function __construct(
        ChatService $chatService, 
        RateLimitService $rateLimitService, 
        AppConfig $config, 
        LoggerInterface $logger,
        TracerInterface $tracer
    ) {
        $this->chatService = $chatService;
        $this->rateLimitService = $rateLimitService;
        $this->config = $config;
        $this->logger = $logger;
        $this->tracer = $tracer;
    }

    public function chat(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $requestId = uniqid('req_', true);
        $startTime = microtime(true);
        
        // Start enhanced tracing span for chat request
        $httpAttributes = OpenTelemetryBootstrap::createHttpAttributes(
            $request->getServerParams(),
            $request->getMethod(),
            (string) $request->getUri()
        );
        
        $span = $this->tracer->spanBuilder('chat_request')
            ->setAttributes(array_merge($httpAttributes, [
                'request.id' => $requestId,
                'service.version' => $this->config->getVersion(),
            ]))
            ->startSpan();
        
        $this->logger->info('Chat request received', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $request->getHeaderLine('User-Agent')
        ]);

        try {
            // Validate HTTP method
            if ($request->getMethod() !== 'POST') {
                $this->logger->warning('Invalid HTTP method', [
                    'request_id' => $requestId,
                    'method' => $request->getMethod()
                ]);
                
                $span->addEvent('invalid_method', [
                    'method' => $request->getMethod()
                ]);
                
                $errorResponse = $this->errorResponse($response, 'Método no permitido', 405);
                $span->setAttributes(['http.status_code' => 405])
                     ->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Method not allowed')
                     ->end();
                return $errorResponse;
            }

            // Check rate limit with enhanced tracing
            $span->addEvent('rate_limit_check_start');
            $rateLimitResult = $this->rateLimitService->checkRateLimit($request);
            $span->addEvent('rate_limit_check_complete', [
                'allowed' => $rateLimitResult['allowed'],
                'remaining' => $rateLimitResult['remaining'],
                'limit' => $rateLimitResult['limit'],
                'reset' => $rateLimitResult['reset']
            ]);
            
            if (!$rateLimitResult['allowed']) {
                $this->logger->warning('Rate limit exceeded', [
                    'request_id' => $requestId,
                    'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                    'remaining' => $rateLimitResult['remaining']
                ]);
                
                $errorResponse = $this->errorResponse($response, 'Límite de requests excedido', 429);
                $span->setAttributes([
                    'http.status_code' => 429,
                    'rate_limit.exceeded' => true,
                    'rate_limit.remaining' => $rateLimitResult['remaining'],
                    'rate_limit.limit' => $rateLimitResult['limit'],
                    'rate_limit.retry_after' => $rateLimitResult['retry_after']
                ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Rate limit exceeded')
                  ->end();
                
                return $errorResponse
                    ->withHeader('X-RateLimit-Limit', (string) $rateLimitResult['limit'])
                    ->withHeader('X-RateLimit-Remaining', (string) $rateLimitResult['remaining'])
                    ->withHeader('X-RateLimit-Reset', (string) $rateLimitResult['reset'])
                    ->withHeader('Retry-After', (string) $rateLimitResult['retry_after']);
            }

            // Obtain and validate input data
            $data = $request->getParsedBody();

            // Fallback to manual parsing if middleware did not process JSON
            if ($data === null) {
                $body = $request->getBody()->getContents();

                // Debug for testing
                if ($this->config->isDevelopment()) {
                    $this->logger->debug('Raw request body', [
                        'body' => $body,
                        'length' => strlen($body)
                    ]);
                }
                
                $data = json_decode($body, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->warning('Invalid JSON in request', [
                        'request_id' => $requestId,
                        'json_error' => json_last_error_msg(),
                        'body_preview' => substr($body, 0, 100)
                    ]);
                    
                    $span->addEvent('invalid_json', [
                        'json_error' => json_last_error_msg(),
                        'body_preview' => substr($body, 0, 100)
                    ]);
                    
                    $errorResponse = $this->errorResponse($response, 'JSON inválido', 400);
                    $span->setAttributes(['http.status_code' => 400])
                         ->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Invalid JSON')
                         ->end();
                    return $errorResponse;
                }
            }
            
            if (!is_array($data)) {
                $errorResponse = $this->errorResponse($response, 'JSON inválido', 400);
                $span->setAttributes(['http.status_code' => 400])
                     ->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Invalid data format')
                     ->end();
                return $errorResponse;
            }

            if (!isset($data['message'])) {
                $this->logger->warning('Missing message field', [
                    'request_id' => $requestId,
                    'received_fields' => array_keys($data)
                ]);
                
                $span->addEvent('missing_message_field', [
                    'received_fields' => array_keys($data)
                ]);
                $errorResponse = $this->errorResponse($response, 'Campo "message" requerido', 400);
                $span->setAttributes(['http.status_code' => 400])
                     ->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Missing message field')
                     ->end();
                return $errorResponse;
            }

            if (!is_string($data['message'])) {
                $this->logger->warning('Invalid message type', [
                    'request_id' => $requestId,
                    'message_type' => gettype($data['message'])
                ]);
                
                $span->addEvent('invalid_message_type', [
                    'message_type' => gettype($data['message'])
                ]);
                $errorResponse = $this->errorResponse($response, 'El campo "message" debe ser una cadena de texto', 400);
                $span->setAttributes(['http.status_code' => 400])
                     ->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Invalid message type')
                     ->end();
                return $errorResponse;
            }

            if (trim($data['message']) === '') {
                $this->logger->warning('Empty message', [
                    'request_id' => $requestId
                ]);
                
                $span->addEvent('empty_message');
                $errorResponse = $this->errorResponse($response, 'El mensaje no puede estar vacío', 400);
                $span->setAttributes(['http.status_code' => 400])
                     ->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Empty message')
                     ->end();
                return $errorResponse;
            }

            // Validate message size (maximum 8KB)
            $messageLength = strlen($data['message']);
            if ($messageLength > 8192) {
                $this->logger->warning('Message too large', [
                    'request_id' => $requestId,
                    'message_length' => $messageLength
                ]);
                
                $span->addEvent('message_too_large', [
                    'message_length' => $messageLength,
                    'max_length' => 8192
                ]);
                $errorResponse = $this->errorResponse($response, 'El mensaje es demasiado largo (máximo 8KB)', 400);
                $span->setAttributes(['http.status_code' => 400])
                     ->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Message too large')
                     ->end();
                return $errorResponse;
            }

            // Validate conversation_id size if present
            $conversationIdCount = 0;
            if (isset($data['conversation_id']) && is_array($data['conversation_id'])) {
                $conversationIdCount = count($data['conversation_id']);
                if ($conversationIdCount > 100) {
                    $this->logger->warning('Conversation ID array too large', [
                        'request_id' => $requestId,
                        'conversation_id_count' => $conversationIdCount
                    ]);
                    
                    $span->addEvent('conversation_id_too_large', [
                        'conversation_id_count' => $conversationIdCount,
                        'max_count' => 100
                    ]);
                    $errorResponse = $this->errorResponse($response, 'El historial de conversación es demasiado largo', 400);
                    $span->setAttributes(['http.status_code' => 400])
                         ->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Conversation history too large')
                         ->end();
                    return $errorResponse;
                }
            }

            // Process message with enhanced tracing
            $span->addEvent('chat_processing_start', [
                'message_length' => $messageLength,
                'conversation_id_count' => $conversationIdCount
            ])->setAttributes([
                'chat.message_length' => $messageLength,
                'chat.conversation_id_count' => $conversationIdCount,
                'chat.message_preview' => substr($data['message'], 0, 100)
            ]);
            
            $result = $this->chatService->processMessage($data['message'], $data['conversation_id'] ?? null, $span);
            
            $span->addEvent('chat_processing_complete', [
                'response_mode' => $result['mode'] ?? 'unknown',
                'response_length' => strlen($result['response'] ?? '')
            ]);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Chat request completed successfully', [
                'request_id' => $requestId,
                'processing_time_ms' => $processingTime,
                'response_mode' => $result['mode'] ?? 'unknown'
            ]);

            // Add enhanced success tracing
            $span->setAttributes([
                'http.status_code' => 200,
                'response.processing_time_ms' => $processingTime,
                'response.mode' => $result['mode'] ?? 'unknown',
                'response.length' => strlen($result['response'] ?? ''),
                'rate_limit.remaining' => $rateLimitResult['remaining']
            ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK)
              ->end();

            // Add rate limit headers to successful response
            $successResponse = $this->successResponse($response, $result);
            
            return $successResponse
                ->withHeader('X-RateLimit-Limit', (string) $rateLimitResult['limit'])
                ->withHeader('X-RateLimit-Remaining', (string) $rateLimitResult['remaining'])
                ->withHeader('X-RateLimit-Reset', (string) $rateLimitResult['reset'])
                ->withHeader('X-Request-ID', $requestId);

        } catch (RuntimeException $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->error('Chat request failed with runtime error', [
                'request_id' => $requestId,
                'processing_time_ms' => $processingTime,
                'error' => $e->getMessage()
            ]);
            
            $span->recordException($e)
                 ->setAttributes([
                     'http.status_code' => 400,
                     'error.processing_time_ms' => $processingTime,
                     'error.type' => 'runtime_error',
                     'error.message' => $e->getMessage()
                 ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage())
                   ->end();
            
            return $this->errorResponse($response, $e->getMessage(), 400)
                ->withHeader('X-Request-ID', $requestId);
                
        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->error('Chat request failed with unexpected error', [
                'request_id' => $requestId,
                'processing_time_ms' => $processingTime,
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            $span->recordException($e)
                 ->setAttributes([
                     'http.status_code' => 500,
                     'error.processing_time_ms' => $processingTime,
                     'error.type' => 'unexpected_error',
                     'error.message' => $e->getMessage(),
                     'error.class' => get_class($e),
                     'error.file' => $e->getFile(),
                     'error.line' => $e->getLine()
                 ])->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, 'Internal server error')
                   ->end();
            
            return $this->errorResponse($response, 'Error interno del servidor', 500)
                ->withHeader('X-Request-ID', $requestId);
        }
    }

    public function options(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Handle preflight CORS
        $this->logger->debug('CORS preflight request handled', [
            'origin' => $request->getHeaderLine('Origin'),
            'method' => $request->getHeaderLine('Access-Control-Request-Method')
        ]);
        
        return $response->withStatus(200);
    }

    private function successResponse(ResponseInterface $response, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    private function errorResponse(ResponseInterface $response, string $message, int $status = 400): ResponseInterface
    {
        $errorData = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ];

        $response->getBody()->write(json_encode($errorData));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}