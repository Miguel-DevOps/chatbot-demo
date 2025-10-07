<?php

declare(strict_types=1);

namespace ChatbotDemo\Controllers;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\RateLimitService;
use ChatbotDemo\Services\TracingService;
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
    private TracingService $tracingService;

    public function __construct(
        ChatService $chatService, 
        RateLimitService $rateLimitService, 
        AppConfig $config, 
        LoggerInterface $logger,
        TracingService $tracingService
    ) {
        $this->chatService = $chatService;
        $this->rateLimitService = $rateLimitService;
        $this->config = $config;
        $this->logger = $logger;
        $this->tracingService = $tracingService;
    }

    public function chat(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $requestId = uniqid('req_', true);
        $startTime = microtime(true);
        
        // Start tracing span for chat request
        $span = $this->tracingService->startSpan('chat_request', [
            'request_id' => $requestId,
            'http.method' => $request->getMethod(),
            'user_agent' => $request->getHeaderLine('User-Agent')
        ]);
        
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
                
                $this->tracingService->addSpanEvent($span, 'invalid_method', [
                    'method' => $request->getMethod()
                ]);
                
                $errorResponse = $this->errorResponse($response, 'Método no permitido', 405);
                $this->tracingService->finishSpan($span, ['http.status_code' => 405]);
                return $errorResponse;
            }

            // Check rate limit with tracing
            $this->tracingService->addSpanEvent($span, 'rate_limit_check_start');
            $rateLimitResult = $this->rateLimitService->checkRateLimit($request);
            $this->tracingService->addSpanEvent($span, 'rate_limit_check_complete', [
                'allowed' => $rateLimitResult['allowed'],
                'remaining' => $rateLimitResult['remaining']
            ]);
            
            if (!$rateLimitResult['allowed']) {
                $this->logger->warning('Rate limit exceeded', [
                    'request_id' => $requestId,
                    'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                    'remaining' => $rateLimitResult['remaining']
                ]);
                
                $errorResponse = $this->errorResponse($response, 'Límite de requests excedido', 429);
                $this->tracingService->finishSpan($span, [
                    'http.status_code' => 429,
                    'rate_limit_exceeded' => true
                ]);
                
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
                    
                    $this->tracingService->addSpanEvent($span, 'invalid_json', [
                        'json_error' => json_last_error_msg()
                    ]);
                    
                    $errorResponse = $this->errorResponse($response, 'JSON inválido', 400);
                    $this->tracingService->finishSpan($span, ['http.status_code' => 400]);
                    return $errorResponse;
                }
            }
            
            if (!is_array($data)) {
                $errorResponse = $this->errorResponse($response, 'JSON inválido', 400);
                $this->tracingService->finishSpan($span, ['http.status_code' => 400]);
                return $errorResponse;
            }

            if (!isset($data['message'])) {
                $this->logger->warning('Missing message field', [
                    'request_id' => $requestId,
                    'received_fields' => array_keys($data)
                ]);
                
                $this->tracingService->addSpanEvent($span, 'missing_message_field');
                $errorResponse = $this->errorResponse($response, 'Campo "message" requerido', 400);
                $this->tracingService->finishSpan($span, ['http.status_code' => 400]);
                return $errorResponse;
            }

            if (!is_string($data['message'])) {
                $this->logger->warning('Invalid message type', [
                    'request_id' => $requestId,
                    'message_type' => gettype($data['message'])
                ]);
                
                $this->tracingService->addSpanEvent($span, 'invalid_message_type');
                $errorResponse = $this->errorResponse($response, 'El campo "message" debe ser una cadena de texto', 400);
                $this->tracingService->finishSpan($span, ['http.status_code' => 400]);
                return $errorResponse;
            }

            if (trim($data['message']) === '') {
                $this->logger->warning('Empty message', [
                    'request_id' => $requestId
                ]);
                
                $this->tracingService->addSpanEvent($span, 'empty_message');
                $errorResponse = $this->errorResponse($response, 'El mensaje no puede estar vacío', 400);
                $this->tracingService->finishSpan($span, ['http.status_code' => 400]);
                return $errorResponse;
            }

            // Validar tamaño del mensaje (máximo 8KB)
            if (strlen($data['message']) > 8192) {
                $this->logger->warning('Message too large', [
                    'request_id' => $requestId,
                    'message_length' => strlen($data['message'])
                ]);
                
                $this->tracingService->addSpanEvent($span, 'message_too_large');
                $errorResponse = $this->errorResponse($response, 'El mensaje es demasiado largo (máximo 8KB)', 400);
                $this->tracingService->finishSpan($span, ['http.status_code' => 400]);
                return $errorResponse;
            }

            // Validar tamaño del conversation_id si existe
            if (isset($data['conversation_id']) && is_array($data['conversation_id'])) {
                if (count($data['conversation_id']) > 100) {
                    $this->logger->warning('Conversation ID array too large', [
                        'request_id' => $requestId,
                        'conversation_id_count' => count($data['conversation_id'])
                    ]);
                    
                    $this->tracingService->addSpanEvent($span, 'conversation_id_too_large');
                    $errorResponse = $this->errorResponse($response, 'El historial de conversación es demasiado largo', 400);
                    $this->tracingService->finishSpan($span, ['http.status_code' => 400]);
                    return $errorResponse;
                }
            }

            // Process message with tracing
            $this->tracingService->addSpanEvent($span, 'chat_processing_start', [
                'message_length' => strlen($data['message'])
            ]);
            
            $result = $this->chatService->processMessage($data['message'], $span);
            
            $this->tracingService->addSpanEvent($span, 'chat_processing_complete');
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Chat request completed successfully', [
                'request_id' => $requestId,
                'processing_time_ms' => $processingTime,
                'response_mode' => $result['mode'] ?? 'unknown'
            ]);

            // Add rate limit headers to successful response
            $successResponse = $this->successResponse($response, $result);
            $this->tracingService->finishSpan($span, [
                'http.status_code' => 200,
                'processing_time_ms' => $processingTime,
                'response_mode' => $result['mode'] ?? 'unknown'
            ]);
            
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
            
            $this->tracingService->finishSpanWithError($span, $e, [
                'http.status_code' => 400,
                'processing_time_ms' => $processingTime
            ]);
            
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
            
            $this->tracingService->finishSpanWithError($span, $e, [
                'http.status_code' => 500,
                'processing_time_ms' => $processingTime
            ]);
            
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