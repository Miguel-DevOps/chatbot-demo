<?php

declare(strict_types=1);

namespace ChatbotDemo\Middleware;

use ChatbotDemo\Config\AppConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Psr7\Response;
use Throwable;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private AppConfig $config;

    public function __construct(LoggerInterface $logger, AppConfig $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            return $this->handleException($request, $exception);
        }
    }

    private function handleException(ServerRequestInterface $request, Throwable $exception): ResponseInterface
    {
        $response = new Response();
        $statusCode = $this->getStatusCode($exception);
        
        // Prepare error context for logging
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_method' => $request->getMethod(),
            'request_uri' => (string) $request->getUri(),
            'request_headers' => $request->getHeaders(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'ip_address' => $this->getClientIpAddress($request)
        ];

        // Add request body for POST requests (excluding sensitive data)
        if ($request->getMethod() === 'POST') {
            $body = (string) $request->getBody();
            if ($body) {
                $context['request_body'] = $this->sanitizeRequestBody($body);
            }
        }

        // Log based on severity
        $this->logException($exception, $statusCode, $context);

        // Prepare error response
        $errorData = $this->prepareErrorResponse($exception, $statusCode);

        $response->getBody()->write(json_encode($errorData, JSON_PRETTY_PRINT));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    private function getStatusCode(Throwable $exception): int
    {
        // HTTP exceptions (from Slim)
        if ($exception instanceof HttpException) {
            return $exception->getCode();
        }

        // Custom business logic exceptions
        $exceptionClass = get_class($exception);
        switch ($exceptionClass) {
            case 'InvalidArgumentException':
            case 'ChatbotDemo\\Exceptions\\ValidationException':
                return 400;
            
            case 'ChatbotDemo\\Exceptions\\UnauthorizedException':
                return 401;
            
            case 'ChatbotDemo\\Exceptions\\ForbiddenException':
                return 403;
            
            case 'ChatbotDemo\\Exceptions\\NotFoundException':
                return 404;
            
            case 'ChatbotDemo\\Exceptions\\RateLimitException':
                return 429;
            
            case 'ChatbotDemo\\Exceptions\\ExternalServiceException':
                return 502;
            
            case 'ChatbotDemo\\Exceptions\\ServiceUnavailableException':
                return 503;
            
            default:
                return 500;
        }
    }

    private function logException(Throwable $exception, int $statusCode, array $context): void
    {
        $message = sprintf(
            'HTTP %d: %s in %s:%d',
            $statusCode,
            $exception->getMessage(),
            basename($exception->getFile()),
            $exception->getLine()
        );

        // Log level based on status code
        if ($statusCode >= 500) {
            // Server errors are critical
            $this->logger->error($message, $context);
            
            // Also log the full stack trace for server errors
            $this->logger->debug('Stack trace: ' . $exception->getTraceAsString(), $context);
        } elseif ($statusCode >= 400) {
            // Client errors are warnings
            $this->logger->warning($message, $context);
        } else {
            // Anything else is info
            $this->logger->info($message, $context);
        }
    }

    private function prepareErrorResponse(Throwable $exception, int $statusCode): array
    {
        $errorData = [
            'error' => true,
            'status' => $statusCode,
            'message' => $this->getPublicErrorMessage($exception, $statusCode),
            'timestamp' => date('c')
        ];

        // Add debug information in development
        if ($this->config->isDevelopment()) {
            $errorData['debug'] = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        // Add error code if it's an HTTP exception
        if ($exception instanceof HttpException && $exception->getTitle()) {
            $errorData['error_code'] = $exception->getTitle();
        }

        return $errorData;
    }

    private function getPublicErrorMessage(Throwable $exception, int $statusCode): string
    {
        // Return safe, user-friendly messages for production
        if (!$this->config->isDevelopment()) {
            switch ($statusCode) {
                case 400:
                    return 'Invalid request. Please check your input and try again.';
                case 401:
                    return 'Authentication required.';
                case 403:
                    return 'Access denied.';
                case 404:
                    return 'The requested resource was not found.';
                case 429:
                    return 'Too many requests. Please try again later.';
                case 500:
                    return 'Internal server error. Please try again later.';
                case 502:
                    return 'External service unavailable. Please try again later.';
                case 503:
                    return 'Service temporarily unavailable. Please try again later.';
                default:
                    return 'An error occurred while processing your request.';
            }
        }

        // Return actual error message in development
        return $exception->getMessage();
    }

    private function getClientIpAddress(ServerRequestInterface $request): string
    {
        // Check for IP from various headers (in order of preference)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            $serverParams = $request->getServerParams();
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                // Handle comma-separated IPs (take the first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    }

    private function sanitizeRequestBody(string $body): string
    {
        // Remove or mask sensitive information from logs
        $decoded = json_decode($body, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Mask common sensitive fields
            $sensitiveFields = ['password', 'token', 'api_key', 'secret', 'authorization'];
            
            foreach ($sensitiveFields as $field) {
                if (isset($decoded[$field])) {
                    $decoded[$field] = '***MASKED***';
                }
            }
            
            return json_encode($decoded);
        }

        // If not JSON, truncate long bodies
        return strlen($body) > 1000 ? substr($body, 0, 1000) . '...[truncated]' : $body;
    }
}