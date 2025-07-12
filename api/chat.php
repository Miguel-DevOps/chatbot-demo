<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // En producción, cambiar por dominio específico
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Rate limiting básico con sesiones
session_start();
$current_time = time();
$window = 900; // 15 minutos
$max_requests = 50; // máximo 50 requests por sesión por ventana

if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = [];
}

// Limpiar requests antiguos
$_SESSION['rate_limit'] = array_filter($_SESSION['rate_limit'], function($timestamp) use ($current_time, $window) {
    return ($current_time - $timestamp) < $window;
});

// Verificar límite
if (count($_SESSION['rate_limit']) >= $max_requests) {
    http_response_code(429);
    echo json_encode(['error' => 'Demasiadas solicitudes. Intenta nuevamente en 15 minutos.']);
    exit();
}

// Agregar request actual
$_SESSION['rate_limit'][] = $current_time;

try {
    // Leer y validar datos de entrada
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }

    if (!isset($data['message']) || empty(trim($data['message']))) {
        throw new Exception('Mensaje requerido');
    }

    $user_message = trim($data['message']);
    
    // Validar longitud del mensaje
    if (strlen($user_message) > 1000) {
        throw new Exception('Mensaje demasiado largo');
    }

    // API Key de Gemini - CONFIGURAR AQUÍ TU API KEY
    $gemini_api_key = 'GEMINI_API_KEY'; // ⚠️ CAMBIAR POR TU API KEY REAL
    
    // Si no hay API Key, devolver respuesta dummy para test local
    if ($gemini_api_key === 'API_KEY_DE_GEMINI_AQUI' || $gemini_api_key === 'GEMINI_API_KEY' || php_sapi_name() === 'cli') {
        echo json_encode([
            'success' => true,
            'response' => 'Respuesta de prueba: el chatbot está funcionando.',
            'timestamp' => date('c')
        ]);
        exit();
    }

    // Cargar knowledge base desde archivo externo
    $knowledge_base = include 'knowledge-base.php';
    $system_prompt = $knowledge_base . $user_message;

    // Preparar datos para la API de Gemini
    $api_data = [
        'contents' => [[
            'parts' => [[
                'text' => $system_prompt
            ]]
        ]],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 1,
            'topP' => 1,
            'maxOutputTokens' => 2048
        ]
    ];

    // Configurar cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $gemini_api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($api_data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'ChatBot/1.0'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('Error de conexión: ' . $curl_error);
    }

    if ($http_code !== 200) {
        throw new Exception('Error en la API de Gemini: ' . $http_code);
    }

    $gemini_response = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Respuesta inválida de la API');
    }

    // Extraer respuesta del bot
    $bot_response = $gemini_response['candidates'][0]['content']['parts'][0]['text'] ?? 
                   'Disculpa, no pude procesar tu consulta en este momento. Por favor intenta nuevamente.';

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'response' => $bot_response,
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
