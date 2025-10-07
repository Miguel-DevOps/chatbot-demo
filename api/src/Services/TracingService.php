<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\NonRecordingSpan;
use Psr\Log\LoggerInterface;

/**
 * OpenTelemetry Tracing Service
 * Provides distributed tracing capabilities using OpenTelemetry API
 */
class TracingService
{
    private TracerInterface $tracer;
    private LoggerInterface $logger;
    private array $activeSpans = [];
    private bool $tracingEnabled = true;

    public function __construct(LoggerInterface $logger, string $serviceName = 'chatbot-api')
    {
        $this->logger = $logger;
        $this->initializeTracer($serviceName);
    }

    private function initializeTracer(string $serviceName): void
    {
        try {
            // Use global tracer provider from OpenTelemetry API
            $tracerProvider = Globals::tracerProvider();
            
            // Check if we have a real tracer provider or just a NoOp
            $this->tracer = $tracerProvider->getTracer(
                $serviceName,
                '1.0.0',
                'https://opentelemetry.io/schemas/1.24.0'
            );

            // Test if tracing is actually working by creating a test span
            $testSpan = $this->tracer->spanBuilder('test-span')->startSpan();
            $isRealTracer = $testSpan->isRecording();
            $testSpan->end();

            if (!$isRealTracer) {
                $this->logger->info('OpenTelemetry not configured, using logging-based tracing', [
                    'service_name' => $serviceName
                ]);
                $this->tracingEnabled = false;
            } else {
                $this->logger->debug('OpenTelemetry tracing initialized successfully', [
                    'service_name' => $serviceName,
                    'implementation' => 'opentelemetry-api'
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize OpenTelemetry tracing, using logging fallback', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e)
            ]);
            
            $this->tracingEnabled = false;
        }
    }

    /**
     * Start a new span
     */
    public function startSpan(string $operationName, array $attributes = [], ?SpanInterface $parentSpan = null): SpanInterface
    {
        try {
            if (!$this->tracingEnabled) {
                // Use logging-based tracing as fallback
                $this->logger->info('Span started (logging fallback)', [
                    'operation_name' => $operationName,
                    'attributes' => $attributes,
                    'timestamp' => microtime(true)
                ]);
                return NonRecordingSpan::getInvalid();
            }

            $spanBuilder = $this->tracer->spanBuilder($operationName);
            
            // Only set parent if it's a valid recording span
            if ($parentSpan !== null && $parentSpan->isRecording()) {
                $spanBuilder->setParent($parentSpan->getContext());
            }

            $span = $spanBuilder
                ->setSpanKind(SpanKind::KIND_INTERNAL)
                ->startSpan();

            // Add attributes
            foreach ($attributes as $key => $value) {
                $span->setAttribute($key, $value);
            }

            $spanId = spl_object_id($span);
            $this->activeSpans[$spanId] = $span;

            $this->logger->debug('Span started', [
                'operation_name' => $operationName,
                'span_id' => $spanId,
                'attributes' => $attributes
            ]);

            return $span;

        } catch (\Exception $e) {
            $this->logger->error('Failed to start span', [
                'operation_name' => $operationName,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to logging
            $this->logger->info('Span started (error fallback)', [
                'operation_name' => $operationName,
                'attributes' => $attributes,
                'error' => $e->getMessage()
            ]);
            
            return NonRecordingSpan::getInvalid();
        }
    }

    /**
     * Start an HTTP span
     */
    public function startHttpSpan(string $method, string $url, array $attributes = []): SpanInterface
    {
        $operationName = sprintf('%s %s', $method, $url);
        
        $httpAttributes = array_merge([
            'http.method' => $method,
            'http.url' => $url,
            'component' => 'http'
        ], $attributes);

        if (!$this->tracingEnabled) {
            return NonRecordingSpan::getInvalid();
        }

        try {
            $span = $this->tracer->spanBuilder($operationName)
                ->setSpanKind(SpanKind::KIND_SERVER)
                ->startSpan();

            foreach ($httpAttributes as $key => $value) {
                $span->setAttribute($key, $value);
            }

            $spanId = spl_object_id($span);
            $this->activeSpans[$spanId] = $span;

            return $span;

        } catch (\Exception $e) {
            $this->logger->error('Failed to start HTTP span', [
                'operation_name' => $operationName,
                'error' => $e->getMessage()
            ]);
            
            return NonRecordingSpan::getInvalid();
        }
    }

    /**
     * Finish a span successfully
     */
    public function finishSpan(SpanInterface $span, array $attributes = []): void
    {
        try {
            if (!$this->tracingEnabled || $span instanceof NonRecordingSpan) {
                // Use logging-based span completion as fallback
                $this->logger->info('Span finished (logging fallback)', [
                    'attributes' => $attributes,
                    'timestamp' => microtime(true),
                    'success' => true
                ]);
                return;
            }

            // Add final attributes
            foreach ($attributes as $key => $value) {
                $span->setAttribute($key, $value);
            }

            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $spanId = spl_object_id($span);
            unset($this->activeSpans[$spanId]);

            $this->logger->debug('Span finished successfully', [
                'span_id' => $spanId
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to finish span', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Finish a span with error
     */
    public function finishSpanWithError(SpanInterface $span, \Throwable $exception, array $context = []): void
    {
        try {
            if (!$this->tracingEnabled || $span instanceof NonRecordingSpan) {
                // Use logging-based span completion as fallback
                $this->logger->error('Span finished with error (logging fallback)', array_merge([
                    'error' => $exception->getMessage(),
                    'exception_class' => get_class($exception),
                    'stack_trace' => $exception->getTraceAsString(),
                    'timestamp' => microtime(true)
                ], $context));
                return;
            }

            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
            
            // Add context as attributes
            foreach ($context as $key => $value) {
                $span->setAttribute($key, $value);
            }
            
            $span->end();

            $spanId = spl_object_id($span);
            unset($this->activeSpans[$spanId]);

            $this->logger->error('Span finished with error', [
                'span_id' => $spanId,
                'error' => $exception->getMessage(),
                'exception_class' => get_class($exception)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to finish span with error', [
                'error' => $e->getMessage(),
                'original_error' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Add event to span
     */
    public function addSpanEvent(SpanInterface $span, string $name, array $attributes = []): void
    {
        try {
            if (!$this->tracingEnabled || $span instanceof NonRecordingSpan) {
                // Use logging-based events as fallback
                $this->logger->info('Span event (logging fallback)', [
                    'event_name' => $name,
                    'attributes' => $attributes,
                    'timestamp' => microtime(true)
                ]);
                return;
            }

            $span->addEvent($name, $attributes);
            
            $this->logger->debug('Event added to span', [
                'span_id' => spl_object_id($span),
                'event_name' => $name,
                'attributes' => $attributes
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to add event to span', [
                'event_name' => $name,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to logging
            $this->logger->info('Span event (error fallback)', [
                'event_name' => $name,
                'attributes' => $attributes,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Wrap a callable with tracing
     */
    public function traceCallable(string $operationName, callable $callable, array $attributes = [], ?SpanInterface $parentSpan = null)
    {
        $span = $this->startSpan($operationName, $attributes, $parentSpan);
        
        try {
            $result = $callable($span);
            $this->finishSpan($span);
            return $result;
        } catch (\Throwable $e) {
            $this->finishSpanWithError($span, $e);
            throw $e;
        }
    }

    /**
     * Get active spans count (for debugging)
     */
    public function getActiveSpansCount(): int
    {
        return count($this->activeSpans);
    }
}