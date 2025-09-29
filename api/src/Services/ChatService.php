<?php

declare(strict_types=1);

namespace ChatbotDemo\Services;

use ChatbotDemo\Config\AppConfig;
use ChatbotDemo\Exceptions\ExternalServiceException;
use ChatbotDemo\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Servicio de Chat con Gemini AI
 * Maneja la comunicación con la API de Google Gemini
 */
class ChatService
{
    private AppConfig $config;
    private KnowledgeBaseService $knowledgeService;
    private LoggerInterface $logger;

    public function __construct(AppConfig $config, KnowledgeBaseService $knowledgeService, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->knowledgeService = $knowledgeService;
        $this->logger = $logger;
    }

    public function processMessage(string $userMessage): array
    {
        $startTime = microtime(true);
        
        $this->logger->info('Processing chat message', [
            'message_length' => strlen($userMessage),
            'message_preview' => substr($userMessage, 0, 100) . (strlen($userMessage) > 100 ? '...' : '')
        ]);

        try {
            // Validar mensaje
            $this->validateMessage($userMessage);
            
            // Obtener API key
            $apiKey = $this->config->getGeminiApiKey();
            
            // Si está en modo demo, devolver respuesta de prueba
            if ($apiKey === 'DEMO_MODE') {
                $this->logger->info('Returning demo response');
                return [
                    'success' => true,
                    'response' => 'Respuesta de prueba: el chatbot está funcionando correctamente. (Modo Demo)',
                    'timestamp' => date('c'),
                    'mode' => 'demo'
                ];
            }

            // Procesar con Gemini AI
            $result = $this->callGeminiAPI($userMessage, $apiKey);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Chat message processed successfully', [
                'processing_time_ms' => $processingTime,
                'response_length' => strlen($result['response'])
            ]);
            
            return $result;

        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->error('Failed to process chat message', [
                'processing_time_ms' => $processingTime,
                'error' => $e->getMessage(),
                'exception_type' => get_class($e)
            ]);
            throw $e;
        }
    }

    private function validateMessage(string $message): void
    {
        $errors = [];
        
        if (empty(trim($message))) {
            $errors[] = 'Mensaje no puede estar vacío';
        }

        if (strlen($message) > 1000) {
            $errors[] = 'Mensaje demasiado largo (máximo 1000 caracteres)';
        }

        // Validación adicional para contenido inapropiado (básico)
        $forbiddenWords = ['spam', 'hack', 'exploit'];
        $messageLower = strtolower($message);
        
        foreach ($forbiddenWords as $word) {
            if (strpos($messageLower, $word) !== false) {
                $errors[] = 'Contenido no permitido detectado';
                break;
            }
        }

        if (!empty($errors)) {
            $this->logger->warning('Message validation failed', [
                'errors' => $errors,
                'message_length' => strlen($message)
            ]);
            throw new ValidationException('Mensaje no válido', $errors);
        }

        $this->logger->debug('Message validation passed', [
            'message_length' => strlen($message)
        ]);
    }

    private function callGeminiAPI(string $userMessage, string $apiKey): array
    {
        $this->logger->info('Calling Gemini API');
        
        try {
            // Obtener knowledge base
            $knowledge = $this->knowledgeService->getKnowledgeBase();
            $fullPrompt = $this->knowledgeService->addUserContext($knowledge, $userMessage);

            $this->logger->debug('Prepared prompt for Gemini', [
                'knowledge_base_length' => strlen($knowledge),
                'full_prompt_length' => strlen($fullPrompt)
            ]);

            // Preparar datos para Gemini
            $requestData = [
                'contents' => [[
                    'parts' => [[
                        'text' => $fullPrompt
                    ]]
                ]],
                'generationConfig' => [
                    'temperature' => $this->config->get('gemini.temperature', 0.7),
                    'topK' => 1,
                    'topP' => 1,
                    'maxOutputTokens' => $this->config->get('gemini.max_tokens', 2048)
                ]
            ];

            // Configurar cURL
            $ch = curl_init();
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->config->get('gemini.model')}:generateContent?key=" . $apiKey;
            $timeout = $this->config->get('gemini.timeout', 30);
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($requestData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: ChatBot-Demo/2.0'
                ],
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true
            ]);

            $apiStartTime = microtime(true);
            $response = curl_exec($ch);
            $apiTime = round((microtime(true) - $apiStartTime) * 1000, 2);
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $this->logger->info('Gemini API response received', [
                'http_code' => $httpCode,
                'api_time_ms' => $apiTime,
                'response_size' => strlen($response),
                'has_curl_error' => !empty($curlError)
            ]);

            if ($curlError) {
                $this->logger->error('Gemini API connection error', ['curl_error' => $curlError]);
                throw new ExternalServiceException('Gemini', 'Error de conexión: ' . $curlError);
            }

            if ($httpCode !== 200) {
                $this->logger->error('Gemini API HTTP error', [
                    'http_code' => $httpCode,
                    'response' => substr($response, 0, 500)
                ]);
                throw new ExternalServiceException('Gemini', "Error en la API de Gemini: HTTP {$httpCode}");
            }

            $geminiResponse = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Gemini API invalid JSON response', [
                    'json_error' => json_last_error_msg(),
                    'response_preview' => substr($response, 0, 200)
                ]);
                throw new ExternalServiceException('Gemini', 'Respuesta inválida de la API');
            }

            // Extraer respuesta
            $botResponse = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? 
                          'Disculpa, no pude procesar tu consulta en este momento. Por favor intenta nuevamente.';

            $this->logger->info('Gemini API call successful', [
                'response_length' => strlen($botResponse),
                'api_time_ms' => $apiTime
            ]);

            return [
                'success' => true,
                'response' => trim($botResponse),
                'timestamp' => date('c'),
                'mode' => 'production'
            ];

        } catch (ExternalServiceException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error in Gemini API call', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw new RuntimeException('Error interno del servidor: ' . $e->getMessage());
        }
    }
}