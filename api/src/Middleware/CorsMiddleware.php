<?php

declare(strict_types=1);

namespace ChatbotDemo\Middleware;

use ChatbotDemo\Config\AppConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Middleware de CORS
 * Maneja las políticas de Cross-Origin Resource Sharing
 */
class CorsMiddleware implements MiddlewareInterface
{
    private AppConfig $config;
    private LoggerInterface $logger;

    public function __construct(AppConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $method = $request->getMethod();
        $requestedMethod = $request->getHeaderLine('Access-Control-Request-Method');
        
        $this->logger->debug('Processing CORS request', [
            'origin' => $origin,
            'method' => $method,
            'requested_method' => $requestedMethod,
            'path' => $request->getUri()->getPath()
        ]);

        $response = $handler->handle($request);
        
        $allowedOrigins = $this->config->get('cors.allowed_origins', ['*']);
        $allowedMethods = $this->config->get('cors.allowed_methods', ['GET', 'POST', 'OPTIONS']);
        $allowedHeaders = $this->config->get('cors.allowed_headers', ['Content-Type']);

        // Determinar origin permitido
        $allowedOrigin = '*';
        $originAllowed = true;
        
        if (!empty($origin) && !in_array('*', $allowedOrigins)) {
            $originAllowed = in_array($origin, $allowedOrigins);
            $allowedOrigin = $originAllowed ? $origin : '';
            
            if (!$originAllowed) {
                $this->logger->warning('CORS origin not allowed', [
                    'origin' => $origin,
                    'allowed_origins' => $allowedOrigins
                ]);
            }
        }

        // Log successful CORS processing
        if ($originAllowed) {
            $this->logger->debug('CORS headers applied', [
                'origin' => $origin,
                'allowed_origin' => $allowedOrigin,
                'method' => $method
            ]);
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $allowedHeaders))
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '86400'); // 24 horas
    }
}