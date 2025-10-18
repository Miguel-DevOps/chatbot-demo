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
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'POST' && str_contains($request->getUri()->getPath(), 'chat')) {
            $data = $request->getParsedBody();

            // Fallback to manual parsing if middleware did not process JSON
            if ($data === null) {
                $body = $request->getBody()->getContents();
                $data = json_decode($body, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->errorResponse('JSON inválido', 400);
                }
            }

            if (!is_array($data)) {
                return $this->errorResponse('JSON inválido', 400);
            }

            if (!isset($data['message'])) {
                return $this->errorResponse('Campo "message" requerido', 400);
            }

            if (!is_string($data['message'])) {
                return $this->errorResponse('El campo "message" debe ser una cadena de texto', 400);
            }

            if (trim($data['message']) === '') {
                return $this->errorResponse('El mensaje no puede estar vacío', 400);
            }

            // Validate message size (maximum 8KB)
            $messageLength = strlen($data['message']);
            if ($messageLength > 8192) {
                return $this->errorResponse('El mensaje es demasiado largo (máximo 8KB)', 400);
            }

            // Validate conversation_id size if present
            if (isset($data['conversation_id']) && is_array($data['conversation_id'])) {
                $conversationIdCount = count($data['conversation_id']);
                if ($conversationIdCount > 100) {
                    return $this->errorResponse('El historial de conversación es demasiado largo', 400);
                }
            }
        }

        return $handler->handle($request);
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