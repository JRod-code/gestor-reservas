<?php
declare(strict_types=1);

// Carga automática de dependencias gestionadas por Composer
require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;
use App\Repository\ReservaRepository;
use App\Controller\ReservaController;

// 1. Inicialización de la infraestructura crítica de datos
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Error de infraestructura en el motor de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$repository = new ReservaRepository($db);
$controller = new ReservaController($repository);

// 2. SECCIÓN DE LA API JSON (Intercepta las peticiones de datos antes de tocar Twig)
if (isset($_GET['api']) && $_GET['api'] === 'reservas') {
    $metodo = $_SERVER['REQUEST_METHOD'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($metodo === 'GET') {
        $id ? $controller->ver($id) : $controller->listar();
    } elseif ($metodo === 'POST') {
        if ($id) {
            $controller->cambiarEstado($id);
        } else {
            $controller->crear();
        }
    } else {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Método HTTP no permitido.'], JSON_UNESCAPED_UNICODE);
    }
    exit; // Fin de la ejecución para las llamadas API. Nunca pasará al bloque inferior.
}

// --------------------------------------------------------------------------
// 3. SECCIÓN DE LAS VISTAS WEB (Solo se ejecuta si NO se consulta la API)
// --------------------------------------------------------------------------
// Creamos una carpeta temporal virtual o verificamos físicamente para que no rompa el navegador al entrar a la raíz
if (!is_dir(__DIR__ . '/../templates')) {
    http_response_code(200);
    echo "<h1>Frente del Backend Operativo</h1>";
    echo "<p>Para consumir los datos del ejercicio, utiliza la URL de la API: <a href='?api=reservas'>?api=reservas</a></p>";
    exit;
}

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false,
    'autoescape' => 'html'
]);

$action = $_GET['action'] ?? 'listado';

try {
    if ($action === 'detalle') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $reserva = $repository->obtenerPorId($id);
        
        if (!$reserva) {
            http_response_code(404);
            echo "<h1>Error 404: La reserva solicitada no existe.</h1>";
            exit;
        }
        
        $historial = $repository->obtenerHistorial($id);
        echo $twig->render('detalle.html.twig', [
            'reserva' => $reserva,
            'eventos' => $historial
        ]);
        exit;
    } 
    
    $reservas = $repository->listar();
    echo $twig->render('listado.html.twig', ['reservas' => $reservas]);
    
} catch (\Exception $e) {
    http_response_code(500);
    error_log("Fallo en renderizado Twig: " . $e->getMessage());
    echo "<h1>Error interno del servidor al procesar la interfaz.</h1>";
}
