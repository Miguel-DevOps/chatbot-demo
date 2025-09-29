<?php

declare(strict_types=1);

namespace ChatbotDemo\Exceptions;

use Exception;

class ExternalServiceException extends Exception
{
    private string $service;
    private array $context;

    public function __construct(string $service, string $message, array $context = [], int $code = 502, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->service = $service;
        $this->context = $context;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}