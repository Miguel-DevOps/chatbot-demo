<?php

declare(strict_types=1);

namespace ChatbotDemo\Storage;

use Prometheus\Storage\Adapter;

/**
 * Simple file-based storage adapter for Prometheus metrics
 * This is a basic implementation for development/testing purposes
 */
class FileStorage implements Adapter
{
    private string $metricsPath;

    public function __construct(string $metricsPath = null)
    {
        $this->metricsPath = $metricsPath ?? sys_get_temp_dir() . '/prometheus_metrics/';
        if (!is_dir($this->metricsPath)) {
            mkdir($this->metricsPath, 0755, true);
        }
    }

    public function collect(): array
    {
        $metrics = [];
        $files = glob($this->metricsPath . '*.json');
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if ($data !== null) {
                    $metrics = array_merge($metrics, $data);
                }
            }
        }
        
        return $metrics;
    }

    public function updateHistogram(array $data): void
    {
        $this->updateMetric('histogram', $data);
    }

    public function updateGauge(array $data): void
    {
        $this->updateMetric('gauge', $data);
    }

    public function updateCounter(array $data): void
    {
        $this->updateMetric('counter', $data);
    }

    public function updateSummary(array $data): void
    {
        $this->updateMetric('summary', $data);
    }

    public function flushMemory(): void
    {
        // Clean up old metrics files (older than 1 hour)
        $files = glob($this->metricsPath . '*.json');
        $threshold = time() - 3600;
        
        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
            }
        }
    }

    private function updateMetric(string $type, array $data): void
    {
        $key = $data['name'] ?? 'unknown';
        $filename = $this->metricsPath . $type . '_' . $key . '.json';
        
        // Read existing data
        $existing = [];
        if (file_exists($filename)) {
            $content = file_get_contents($filename);
            if ($content !== false) {
                $existing = json_decode($content, true) ?? [];
            }
        }
        
        // Merge with new data
        $existing[] = $data;
        
        // Write back to file
        file_put_contents($filename, json_encode($existing, JSON_PRETTY_PRINT));
    }
}