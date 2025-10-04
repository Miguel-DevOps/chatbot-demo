<?php

declare(strict_types=1);

namespace ChatbotDemo\Controllers;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Services\ChatService;
use ChatbotDemo\Services\RateLimitService;
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

    public function __construct(
        ChatService $chatService, 
        RateLimitService $rateLimitService, 
        AppConfig $config, 
        LoggerInterface $logger
    ) {
        $this->chatService = $chatService;
        $this->rateLimitService = $rateLimitService;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function chat(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $requestId = uniqid('req_', true);
        $startTime = microtime(true);
        
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
                return $this->errorResponse($response, 'Método no permitido', 405);
            }

            // Check rate limit
            $rateLimitResult = $this->rateLimitService->checkRateLimit($request);
            
            if (!$rateLimitResult['allowed']) {
                $this->logger->warning('Rate limit exceeded', [
                    'request_id' => $requestId,
                    'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                    'remaining' => $rateLimitResult['remaining']
                ]);
                
                $errorResponse = $this->errorResponse($response, 'Límite de requests excedido', 429);
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
                    return $this->errorResponse($response, 'JSON inválido', 400);
                }
            }
            
            if (!is_array($data)) {
                return $this->errorResponse($response, 'JSON inválido', 400);
            }

            if (!isset($data['message'])) {
                $this->logger->warning('Missing message field', [
                    'request_id' => $requestId,
                    'received_fields' => array_keys($data)
                ]);
                return $this->errorResponse($response, 'Campo "message" requerido', 400);
            }

            // Process message
            $result = $this->chatService->processMessage($data['message']);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logger->info('Chat request completed successfully', [
                'request_id' => $requestId,
                'processing_time_ms' => $processingTime,
                'response_mode' => $result['mode'] ?? 'unknown'
            ]);

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