<?php
/**
 * api/index.php
 * Backend MongoDB — Clínica Dental Gonzalez
 *
 * Parámetros GET:
 *   col    => nombre de la colección (pacientes, empleados, pagos, citas, tratamiento, historial_clinico)
 *   accion => insertar | listar
 *
 * Requiere: ext-mongodb (driver PECL) + librería mongodb/mongodb vía Composer
 * URI Atlas: mongodb+srv://Gael:qaws120987@cluster0.m0bbeek.mongodb.net/?appName=Cluster0
 */

// ── Headers CORS / JSON ───────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Pre-flight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Autoload Composer (mongodb/mongodb) ──────────────────────────
// Ajusta la ruta si tu vendor/ está en otro directorio
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo json_encode(['error' => 'vendor/autoload.php no encontrado. Ejecuta: composer require mongodb/mongodb']);
    exit;
}
require_once $autoload;

// ── Configuración ────────────────────────────────────────────────
const MONGO_URI  = 'mongodb+srv://Gael:qaws120987@cluster0.m0bbeek.mongodb.net/?appName=Cluster0';
const MONGO_DB   = 'clinica_dental';

// Colecciones permitidas (whitelist — evita inyección de nombres)
const COLECCIONES_PERMITIDAS = [
    'pacientes',
    'empleados',
    'pagos',
    'citas',
    'tratamiento',
    'historial_clinico',
];

// ── Validar parámetros ───────────────────────────────────────────
$col    = $_GET['col']    ?? '';
$accion = $_GET['accion'] ?? '';

if (!in_array($col, COLECCIONES_PERMITIDAS, true)) {
    http_response_code(400);
    echo json_encode(['error' => "Colección inválida: '$col'"]);
    exit;
}

if (!in_array($accion, ['insertar', 'listar'], true)) {
    http_response_code(400);
    echo json_encode(['error' => "Acción inválida: '$accion'"]);
    exit;
}

// ── Conexión MongoDB ─────────────────────────────────────────────
try {
    $cliente = new MongoDB\Client(MONGO_URI);
    $db      = $cliente->selectDatabase(MONGO_DB);
    $coleccion = $db->selectCollection($col);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Conexión fallida: ' . $e->getMessage()]);
    exit;
}

// ── ACCION: insertar ─────────────────────────────────────────────
if ($accion === 'insertar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Se requiere método POST para insertar']);
        exit;
    }

    // Leer body JSON
    $body = file_get_contents('php://input');
    $datos = json_decode($body, true);

    if (!is_array($datos) || empty($datos)) {
        http_response_code(400);
        echo json_encode(['error' => 'Body JSON vacío o inválido']);
        exit;
    }

    // Sanitizar: quitar campos vacíos opcionales pero conservar los que tienen valor
    $datos = array_filter($datos, fn($v) => $v !== '' && $v !== null);

    // Agregar timestamp de creación
    $datos['creado_en'] = date('Y-m-d H:i:s');

    try {
        $resultado = $coleccion->insertOne($datos);
        echo json_encode([
            'ok'         => true,
            'insertedId' => (string) $resultado->getInsertedId(),
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al insertar: ' . $e->getMessage()]);
    }
    exit;
}

// ── ACCION: listar ───────────────────────────────────────────────
if ($accion === 'listar') {
    try {
        // Devuelve los últimos 100 documentos, más recientes primero
        $cursor = $coleccion->find(
            [],
            [
                'sort'  => ['_id' => -1],
                'limit' => 100,
            ]
        );

        $documentos = [];
        foreach ($cursor as $doc) {
            // Convertir BSON a array y serializar _id como string
            $arr = iterator_to_array($doc);
            $arr['_id'] = (string) $arr['_id'];
            $documentos[] = $arr;
        }

        echo json_encode(['ok' => true, 'documentos' => $documentos]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al listar: ' . $e->getMessage()]);
    }
    exit;
}