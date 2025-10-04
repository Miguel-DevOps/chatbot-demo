<?php

declare(strict_types=1);

namespace ChatbotDemo\Middleware;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Services\RateLimitService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Rate Limiting Middleware
 * Protects the API against abuse and DDoS attacks
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimitService $rateLimitService;
    private AppConfig $config;

    public function __construct(RateLimitService $rateLimitService, AppConfig $config)
    {
        $this->rateLimitService = $rateLimitService;
        $this->config = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check rate limiting
        $rateLimitInfo = $this->rateLimitService->checkRateLimit($request);

        // Create response with rate limiting headers
        $response = $handler->handle($request);
        
        $response = $response
            ->withHeader('X-RateLimit-Limit', (string) $rateLimitInfo['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $rateLimitInfo['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $rateLimitInfo['reset']);

        // If exceeded the limit, return 429
        if (!$rateLimitInfo['allowed']) {
            $response = new Response();
            $response = $response
                ->withStatus(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-RateLimit-Limit', (string) $rateLimitInfo['limit'])
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string) $rateLimitInfo['reset'])
                ->withHeader('Retry-After', (string) $rateLimitInfo['retry_after']);

            $body = json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Try again in ' . round($rateLimitInfo['retry_after'] / 60) . ' minutes.',
                'retry_after' => $rateLimitInfo['retry_after']
            ]);
            
            $response->getBody()->write($body);
        }

        return $response;
    }
}