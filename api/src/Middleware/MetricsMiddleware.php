<?php

declare(strict_types=1);

namespace ChatbotDemo\Middleware;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;
use Prometheus\Storage\InMemory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class MetricsMiddleware implements MiddlewareInterface
{
    private CollectorRegistry $registry;
    private Counter $requestCounter;
    private Histogram $requestDuration;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, ?CollectorRegistry $registry = null)
    {
        $this->logger = $logger;
        $this->registry = $registry ?? new CollectorRegistry(new InMemory());
        
        // Initialize metrics
        $this->initializeMetrics();
    }

    private function initializeMetrics(): void
    {
        try {
            // HTTP requests total counter - check if already registered
            try {
                $this->requestCounter = $this->registry->getCounter('chatbot_api', 'http_requests_total');
            } catch (\Exception $e) {
                $this->requestCounter = $this->registry->registerCounter(
                    'chatbot_api',
                    'http_requests_total',
                    'Total number of HTTP requests',
                    ['method', 'endpoint', 'status_code']
                );
            }

            // HTTP request duration histogram - check if already registered
            try {
                $this->requestDuration = $this->registry->getHistogram('chatbot_api', 'http_request_duration_seconds');
            } catch (\Exception $e) {
                $this->requestDuration = $this->registry->registerHistogram(
                    'chatbot_api',
                    'http_request_duration_seconds',
                    'HTTP request duration in seconds',
                    ['method', 'endpoint'],
                    [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
                );
            }

            $this->logger->debug('Metrics middleware initialized successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize metrics', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e)
            ]);
            throw $e;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        $method = $request->getMethod();
        $endpoint = $this->normalizeEndpoint($request->getUri()->getPath());

        try {
            // Process the request
            $response = $handler->handle($request);
            
            // Calculate duration
            $duration = microtime(true) - $startTime;
            $statusCode = (string) $response->getStatusCode();

            // Record metrics
            $this->recordMetrics($method, $endpoint, $statusCode, $duration);

            return $response;

        } catch (\Exception $e) {
            // Record metrics for errors
            $duration = microtime(true) - $startTime;
            $statusCode = '500'; // Default to 500 for uncaught exceptions
            
            $this->recordMetrics($method, $endpoint, $statusCode, $duration);
            
            throw $e;
        }
    }

    private function recordMetrics(string $method, string $endpoint, string $statusCode, float $duration): void
    {
        try {
            // Increment request counter - usando la API correcta
            $this->requestCounter->incBy(1, [$method, $endpoint, $statusCode]);

            // Record request duration - usando la API correcta
            $this->requestDuration->observe($duration, [$method, $endpoint]);

            $this->logger->debug('Metrics recorded', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'duration_seconds' => round($duration, 4)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to record metrics', [
                'error' => $e->getMessage(),
                'method' => $method,
                'endpoint' => $endpoint,
                'status_code' => $statusCode
            ]);
        }
    }

    private function normalizeEndpoint(string $path): string
    {
        // Normalize common paths to reduce cardinality
        $patterns = [
            '/^\/api\/v1\/chat$/' => '/api/v1/chat',
            '/^\/api\/v1\/health$/' => '/api/v1/health',
            '/^\/api\/v1\/metrics$/' => '/api/v1/metrics',
            '/^\/chat(\.php)?$/' => '/chat',
            '/^\/health(\.php)?$/' => '/health',
            '/^\/metrics$/' => '/metrics',
            '/^\/$/' => '/',
        ];

        foreach ($patterns as $pattern => $normalized) {
            if (preg_match($pattern, $path)) {
                return $normalized;
            }
        }

        // For unknown paths, use a generic label to avoid high cardinality
        return '/unknown';
    }

    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }
}