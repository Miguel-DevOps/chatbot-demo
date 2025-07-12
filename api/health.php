<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Health check simple
if (isset($_GET['plain']) || php_sapi_name() === 'cli') {
    echo 'OK';
} else {
    echo json_encode([
        'status' => 'ok',
        'service' => 'Chatbot API',
        'timestamp' => date('c'),
        'version' => '1.0.0'
    ]);
}
?>
