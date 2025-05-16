<?php

// Este archivo actúa como el punto de entrada (router) para tu API

// --- Configuración y carga de dependencias ---
require_once __DIR__ . '/config.php';
// require_once __DIR__ . '/helpers.php'; // helpers con sendResponse y sendError

require_once __DIR__ . '/controllers/IncidentController.php';
require_once __DIR__ . '/controllers/UserController.php';


// --- Configurar encabezados CORS y de respuesta ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


// --- Procesamiento de la Petición ---

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'];

$base_path = '/api/';

if (substr($request_uri, 0, strlen($base_path)) === $base_path) {
    $request_uri = substr($request_uri, strlen($base_path));
}

$uri_segments = explode('/', trim($request_uri, '/'));

$resource = $uri_segments[0] ?? '';
$id = $uri_segments[1] ?? null;

if (count($uri_segments) > 2 || (count($uri_segments) == 2 && !is_numeric($id) && $id !== null)) { // Permitir /resource/ID pero no /resource/extra
     sendError("Ruta inválida", 404);
     exit();
}


// Instanciar controladores
$incidentController = new IncidentController();
$userController = new UserController();


// --- Enrutamiento ---

switch ($resource) {
    case 'incidents':
        switch ($request_method) {
            case 'GET':
                // *********************************************************
                // MODIFICACIÓN: Obtener parámetro 'filter' de la query string
                // *********************************************************
                $filter = $_GET['filter'] ?? null; // Obtiene ?filter=all o null si no está
                if ($id !== null) {
                    // GET /api/incidents/{id} - El filtro no aplica aquí
                    $incidentController->getOne($id);
                } else {
                    // GET /api/incidents o GET /api/incidents?filter=all
                    $incidentController->getAll($filter); // Pasa el filtro al controlador
                }
                break;
            case 'POST':
                if ($id !== null) { sendError("POST no es permitido con un ID de recurso para incidencias", 405); }
                else { $incidentController->create(); }
                break;
            case 'PUT':
                if ($id !== null) { $incidentController->update($id); }
                else { sendError("PUT requiere un ID de incidencia", 400); }
                break;
            case 'DELETE':
               if ($id !== null) { $incidentController->delete($id); }
               else { sendError("DELETE requiere un ID de incidencia", 400); }
                break;
            default:
                sendError("Método no permitido para el recurso incidents", 405);
                break;
        }
        break;

    case 'users': // --- RUTA COMPLETA PARA USUARIOS (sin cambios en esta modificación) ---
        switch ($request_method) {
            case 'GET':
                if ($id !== null) { $userController->getOne($id); }
                else { $userController->getAll(); }
                break;
            case 'POST':
                 if ($id !== null) { sendError("POST no es permitido con un ID de recurso para usuarios", 405); }
                 else { $userController->create(); }
                break;
            case 'PUT':
                if ($id !== null) { $userController->update($id); }
                else { sendError("PUT requiere un ID de usuario", 400); }
                break;
            case 'DELETE':
               if ($id !== null) { $userController->delete($id); }
               else { sendError("DELETE requiere un ID de usuario", 400); }
                break;
            default:
                sendError("Método no permitido para el recurso users", 405);
                break;
        }
        break;

    case '': // Petición a /api/ sin recurso
        sendResponse(["message" => "API de Gestión de Incidencias - V1. Recursos disponibles: /incidents, /users"], 200);
        break;

    default:
        sendError("Recurso no encontrado", 404);
        break;
}
?>