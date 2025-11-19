<?php
// Disable error display, log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Enable CORS for React frontend
$allowed_origins = ['http://localhost:5173', 'http://localhost:5174'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Your existing routing code below...

// Autoload classes or require needed files
require_once __DIR__ . "/../src/utils/Database.php";
require_once __DIR__ . "/../src/controllers/ProductController.php";

use App\Controllers\ProductController;

try {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];

    // Remove the base path if present (for WAMP or subdirectory installations)
    $uri = preg_replace('#^/carthage-tech-backend/public#', '', $uri);

    if ($uri === "/api/products" && $method === "GET") {
        ProductController::getAll();
    } elseif (preg_match("#^/api/products/([a-zA-Z0-9_-]+)$#", $uri, $matches) && $method === "GET") {
        ProductController::getBySlug($matches[1]);
    } else {
        echo json_encode(["status" => "error", "message" => "Route not found", "uri" => $uri]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
?>
