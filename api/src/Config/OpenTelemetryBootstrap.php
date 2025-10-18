<?php

declare(strict_types=1);

namespace ChatbotDemo\Config;

/**
 * OpenTelemetry SDK Configuration for PHP
 * This file configures the OpenTelemetry SDK with proper exporters
 */

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\SdkBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryBootstrap
{
    private static bool $initialized = false;

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        try {
            // Get configuration from environment
            $serviceName = $_ENV['OTEL_SERVICE_NAME'] ?? 'chatbot-demo-api';
            $serviceVersion = $_ENV['OTEL_SERVICE_VERSION'] ?? '1.0.0';
            $environment = $_ENV['APP_ENV'] ?? 'development';
            $otlpEndpoint = $_ENV['OTEL_EXPORTER_OTLP_ENDPOINT'] ?? 'http://otel-collector:4318';
            
            // Only initialize if tracing is enabled
            $tracingEnabled = ($_ENV['OTEL_TRACES_ENABLED'] ?? 'true') === 'true';
            
            if (!$tracingEnabled) {
                error_log('OpenTelemetry tracing disabled via configuration');
                self::$initialized = true;
                return;
            }

            // Create resource with service information
            $resource = ResourceInfoFactory::defaultResource()->merge(
                ResourceInfo::create(
                    Attributes::create([
                        ResourceAttributes::SERVICE_NAME => $serviceName,
                        ResourceAttributes::SERVICE_VERSION => $serviceVersion,
                        ResourceAttributes::SERVICE_NAMESPACE => 'chatbot-demo',
                        ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $environment,
                        'application.name' => 'Chatbot Demo API',
                        'application.owner' => 'miguel-devops'
                    ])
                )
            );

            // Create OTLP HTTP transport
            $transport = (new OtlpHttpTransportFactory())->create(
                $otlpEndpoint . '/v1/traces',
                'application/x-protobuf',
                []  // headers
            );

            // Create span exporter
            $spanExporter = new SpanExporter($transport);

            // Create batch span processor
            $spanProcessor = new BatchSpanProcessor(
                $spanExporter,
                \OpenTelemetry\SDK\Common\Time\ClockFactory::getDefault(),
                512,  // maxQueueSize
                5000, // scheduledDelayMillis
                30000, // exportTimeoutMillis
                256   // maxExportBatchSize
            );

            // Create tracer provider
            $tracerProvider = (new TracerProviderBuilder())
                ->addSpanProcessor($spanProcessor)
                ->setResource($resource)
                ->build();

            // Initialize SDK
            Sdk::builder()
                ->setTracerProvider($tracerProvider)
                ->setPropagator(TraceContextPropagator::getInstance())
                ->setAutoShutdown(true)
                ->buildAndRegisterGlobal();

            error_log(sprintf(
                'OpenTelemetry SDK initialized: service=%s, version=%s, environment=%s, endpoint=%s',
                $serviceName,
                $serviceVersion,
                $environment,
                $otlpEndpoint
            ));

            self::$initialized = true;

        } catch (\Exception $e) {
            error_log('Failed to initialize OpenTelemetry SDK: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Don't throw exception - let application continue without tracing
            self::$initialized = true;
        }
    }

    public static function isInitialized(): bool
    {
        return self::$initialized;
    }

    /**
     * Create enriched span attributes from HTTP request
     */
    public static function createHttpAttributes(array $serverParams, string $method, string $uri): array
    {
        return [
            'http.method' => $method,
            'http.url' => $uri,
            'http.scheme' => $serverParams['REQUEST_SCHEME'] ?? 'http',
            'http.host' => $serverParams['HTTP_HOST'] ?? 'localhost',
            'http.target' => $serverParams['REQUEST_URI'] ?? $uri,
            'http.user_agent' => $serverParams['HTTP_USER_AGENT'] ?? 'unknown',
            'http.remote_addr' => $serverParams['REMOTE_ADDR'] ?? 'unknown',
            'http.request_content_length' => $serverParams['CONTENT_LENGTH'] ?? null,
            'user.ip' => self::getClientIp($serverParams),
            'request.id' => self::generateRequestId(),
        ];
    }

    /**
     * Create enriched span attributes for AI interactions
     */
    public static function createAiAttributes(string $provider, string $model, array $context = []): array
    {
        return array_merge([
            'ai.provider' => $provider,
            'ai.model' => $model,
            'ai.system' => 'chatbot',
            'component' => 'ai-service'
        ], $context);
    }

    /**
     * Create enriched span attributes for database operations
     */
    public static function createDbAttributes(string $operation, ?string $table = null): array
    {
        $attrs = [
            'db.system' => 'redis',
            'db.operation' => $operation,
            'component' => 'database'
        ];

        if ($table) {
            $attrs['db.table'] = $table;
        }

        return $attrs;
    }

    private static function getClientIp(array $serverParams): string
    {
        // Check for IP from various headers (proxy-aware)
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = trim(explode(',', $serverParams[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    private static function generateRequestId(): string
    {
        return 'req_' . uniqid() . '.' . random_int(10000000, 99999999);
    }
}