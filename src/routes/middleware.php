<?php
// src/routes/middleware.php
// JSON response helper and simple Bearer token auth utilities

use App\Utils\Database;

if (!function_exists('send_json')) {
    function send_json(string $status, string $message, $data = null, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

if (!function_exists('get_path')) {
    function get_path(): string {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($scriptDir !== '' && $scriptDir !== '/') {
            $path = '/' . ltrim(preg_replace('#^' . preg_quote($scriptDir, '#') . '#', '', $path), '/');
        }
        return $path === '' ? '/' : $path;
    }
}

if (!function_exists('get_authorization_header')) {
    function get_authorization_header(): ?string {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }
        // Normalize
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
        return $auth ? trim($auth) : null;
    }
}

if (!function_exists('get_bearer_token')) {
    function get_bearer_token(): ?string {
        $auth = get_authorization_header();
        if ($auth && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        return null;
    }
}

if (!function_exists('get_json_input')) {
    function get_json_input(): array {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('generate_token')) {
    function generate_token(int $userId): string {
        return base64_encode($userId . ':' . time());
    }
}

if (!function_exists('parse_token')) {
    function parse_token(string $token): ?array {
        $decoded = base64_decode($token, true);
        if ($decoded === false) return null;
        $parts = explode(':', $decoded);
        if (count($parts) < 2) return null;
        return ['user_id' => (int)$parts[0], 'ts' => (int)$parts[1]];
    }
}

if (!function_exists('require_auth')) {
    function require_auth(): int {
        $token = get_bearer_token();
        if (!$token) {
            send_json('error', 'Missing Authorization bearer token', null, 401);
        }
        $parsed = parse_token($token);
        if (!$parsed || empty($parsed['user_id'])) {
            send_json('error', 'Invalid token', null, 401);
        }
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->bind_param('i', $parsed['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();
        if (!$user) {
            send_json('error', 'User not found', null, 401);
        }
        return (int)$parsed['user_id'];
    }
}
