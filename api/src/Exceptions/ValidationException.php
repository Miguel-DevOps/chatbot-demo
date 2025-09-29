<?php

declare(strict_types=1);

namespace ChatbotDemo\Exceptions;

use Exception;

class ValidationException extends Exception
{
    private array $errors;

    public function __construct(string $message, array $errors = [], int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}