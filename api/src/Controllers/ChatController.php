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

        $span = $this->tracer->spanBuilder('chat_request')->startSpan();
        $this->logger->info('Chat request received', ['request_id' => $requestId]);

        try {
            // Rate limit check
            $rateLimitResult = $this->rateLimitService->checkRateLimit($request);
            if (!$rateLimitResult['allowed']) {
                return $this->errorResponse($response, 'Límite de requests excedido', 429)
                    ->withHeader('Retry-After', (string) $rateLimitResult['retry_after']);
            }

            // Validation is already done by ValidationMiddleware
            $data = $request->getParsedBody();
            $userMessage = $data['message'];
            $conversationId = $data['conversation_id'] ?? null;

            // Process message
            $result = $this->chatService->processMessage($userMessage, $conversationId, $span);

            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Chat request completed', ['request_id' => $requestId, 'processing_time_ms' => $processingTime]);

            return $this->successResponse($response, $result)
                ->withHeader('X-Request-ID', $requestId);

        } catch (\Exception $e) {
            $this->logger->error('Chat request failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            $span->recordException($e)->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
            return $this->errorResponse($response, 'Error interno del servidor', 500)
                ->withHeader('X-Request-ID', $requestId);
        } finally {
            $span->end();
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