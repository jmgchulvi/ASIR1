<?php
// Configuración de la base de datos
define('DB_HOST', 'db'); // Nombre del servicio de la BD en docker-compose.yml
define('DB_USER', 'fixit'); // El usuario que definiste
define('DB_PASSWORD', 'alumnoalumno'); // La contraseña que definiste
define('DB_NAME', 'fixit'); // El nombre de la base de datos que definiste

// Configuración CORS (permite peticiones desde cualquier origen para desarrollo)
header("Access-Control-Allow-Origin: *"); // Cambia * por tu dominio en producción
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar peticiones OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Función para enviar respuestas JSON
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

// Función para enviar respuestas de error JSON
function sendError($message, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode(["error" => $message]);
    exit();
}
?>
