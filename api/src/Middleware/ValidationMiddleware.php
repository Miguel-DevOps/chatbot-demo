<?php
declare(strict_types=1);

namespace ChatbotDemo\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class ValidationMiddleware implements MiddlewareInterface
{
    private const MAX_MESSAGE_LENGTH = 1000; // Business rule: reasonable message length
    private const MIN_MESSAGE_LENGTH = 1;   // Minimum message length
    private const MAX_CONVERSATION_HISTORY = 100; // Maximum conversation history items
    
    // Security patterns - potentially dangerous content
    private const FORBIDDEN_PATTERNS = [
        // Script injection attempts
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        // JavaScript URI schemes
        '/javascript\s*:/i',
        // Data URI schemes with potential scripts
        '/data\s*:\s*text\/html/i',
        // SQL injection patterns
        '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b).*(\bFROM\b|\bWHERE\b|\bINTO\b)/i',
        // Command injection
        '/(\||;|&|\$\(|\`)/i',
        // Excessive special characters (potential attack)
        '/[<>{}()"\'\`]{10,}/',
    ];
    
    // Rate limiting per content (simple spam detection)
    private const MAX_REPEATED_CHARS = 50; // Maximum consecutive repeated characters

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'POST' && str_contains($request->getUri()->getPath(), 'chat')) {
            $validationResult = $this->validateChatRequest($request);
            
            if (!$validationResult['isValid']) {
                return $this->errorResponse($validationResult['error'], $validationResult['statusCode']);
            }
        }

        return $handler->handle($request);
    }

    private function validateChatRequest(ServerRequestInterface $request): array
    {
        $data = $request->getParsedBody();

        // Fallback to manual parsing if middleware did not process JSON
        if ($data === null) {
            $body = $request->getBody()->getContents();
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'isValid' => false,
                    'error' => 'Formato JSON inválido',
                    'statusCode' => 400
                ];
            }
        }

        if (!is_array($data)) {
            return [
                'isValid' => false,
                'error' => 'El cuerpo de la petición debe ser un objeto JSON',
                'statusCode' => 400
            ];
        }

        // Validate required message field
        if (!isset($data['message'])) {
            return [
                'isValid' => false,
                'error' => 'Campo "message" es requerido',
                'statusCode' => 400
            ];
        }

        if (!is_string($data['message'])) {
            return [
                'isValid' => false,
                'error' => 'El campo "message" debe ser una cadena de texto',
                'statusCode' => 400
            ];
        }

        // Validate message content
        $messageValidation = $this->validateMessageContent($data['message']);
        if (!$messageValidation['isValid']) {
            return $messageValidation;
        }

        // Validate conversation_id if present
        if (isset($data['conversation_id'])) {
            $conversationValidation = $this->validateConversationId($data['conversation_id']);
            if (!$conversationValidation['isValid']) {
                return $conversationValidation;
            }
        }

        return ['isValid' => true];
    }

    private function validateMessageContent(string $message): array
    {
        $trimmedMessage = trim($message);
        
        // Check if empty
        if ($trimmedMessage === '') {
            return [
                'isValid' => false,
                'error' => 'El mensaje no puede estar vacío',
                'statusCode' => 400
            ];
        }

        // Check minimum length
        if (mb_strlen($trimmedMessage) < self::MIN_MESSAGE_LENGTH) {
            return [
                'isValid' => false,
                'error' => 'El mensaje es demasiado corto',
                'statusCode' => 400
            ];
        }

        // Check maximum length (business rule)
        if (mb_strlen($trimmedMessage) > self::MAX_MESSAGE_LENGTH) {
            return [
                'isValid' => false,
                'error' => sprintf('El mensaje es demasiado largo (máximo %d caracteres)', self::MAX_MESSAGE_LENGTH),
                'statusCode' => 400
            ];
        }

        // Check for excessive repeated characters (spam detection)
        if ($this->hasExcessiveRepeatedChars($trimmedMessage)) {
            return [
                'isValid' => false,
                'error' => 'El mensaje contiene demasiados caracteres repetidos',
                'statusCode' => 400
            ];
        }

        // Security validation - check for malicious patterns
        $securityValidation = $this->validateMessageSecurity($trimmedMessage);
        if (!$securityValidation['isValid']) {
            return $securityValidation;
        }

        // Check for null bytes (security)
        if (strpos($trimmedMessage, "\0") !== false) {
            return [
                'isValid' => false,
                'error' => 'El mensaje contiene caracteres no válidos',
                'statusCode' => 400
            ];
        }

        return ['isValid' => true];
    }

    private function validateMessageSecurity(string $message): array
    {
        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $message)) {
                return [
                    'isValid' => false,
                    'error' => 'El mensaje contiene contenido no permitido',
                    'statusCode' => 400
                ];
            }
        }

        return ['isValid' => true];
    }

    private function hasExcessiveRepeatedChars(string $message): bool
    {
        return preg_match('/(.)\1{' . (self::MAX_REPEATED_CHARS - 1) . ',}/', $message) === 1;
    }

    private function validateConversationId($conversationId): array
    {
        if (is_array($conversationId)) {
            $conversationIdCount = count($conversationId);
            if ($conversationIdCount > self::MAX_CONVERSATION_HISTORY) {
                return [
                    'isValid' => false,
                    'error' => sprintf('El historial de conversación es demasiado largo (máximo %d elementos)', self::MAX_CONVERSATION_HISTORY),
                    'statusCode' => 400
                ];
            }
        }

        return ['isValid' => true];
    }

    private function errorResponse(string $message, int $status = 400): ResponseInterface
    {
        $response = new Response();
        $errorData = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ];

        $response->getBody()->write(json_encode($errorData));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}