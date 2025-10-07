<?php

declare(strict_types=1);

namespace ChatbotDemo\Controllers;

use ChatbotDemo\Middleware\MetricsMiddleware;
use Prometheus\RenderTextFormat;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

/**
 * Metrics Controller
 * Exposes Prometheus metrics for monitoring
 */
class MetricsController
{
    private MetricsMiddleware $metricsMiddleware;
    private LoggerInterface $logger;

    public function __construct(MetricsMiddleware $metricsMiddleware, LoggerInterface $logger)
    {
        $this->metricsMiddleware = $metricsMiddleware;
        $this->logger = $logger;
    }

    public function metrics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->logger->debug('Metrics endpoint accessed', [
                'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $request->getHeaderLine('User-Agent')
            ]);

            $registry = $this->metricsMiddleware->getRegistry();
            $renderer = new RenderTextFormat();
            $result = $renderer->render($registry->getMetricFamilySamples());

            $response->getBody()->write($result);
            
            return $response
                ->withHeader('Content-Type', RenderTextFormat::MIME_TYPE)
                ->withStatus(200);

        } catch (\Exception $e) {
            $this->logger->error('Failed to render metrics', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            $errorData = [
                'error' => 'Failed to render metrics',
                'message' => 'Internal server error',
                'timestamp' => date('c')
            ];

            $response->getBody()->write(json_encode($errorData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}