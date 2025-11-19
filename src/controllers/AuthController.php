<?php
// src/controllers/AuthController.php

namespace App\Controllers;

use App\Utils\Database;

class AuthController {
    // POST /api/auth/register
    public static function register(): void {
        $data = \get_json_input();
        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            \send_json('error', 'name, email and password are required', null, 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            \send_json('error', 'Invalid email format', null, 400);
        }

        $conn = Database::getConnection();

        // Check duplicate
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->fetch_assoc()) {
            $stmt->close();
            \send_json('error', 'Email already registered', null, 409);
        }
        $stmt->close();

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('INSERT INTO users (name, email, password_hash) VALUES (?,?,?)');
        $stmt->bind_param('sss', $name, $email, $hash);
        $stmt->execute();
        $userId = $stmt->insert_id;
        $stmt->close();

        $token = \generate_token((int)$userId);
        \send_json('success', 'Registered successfully', [
            'token' => $token,
            'user' => [
                'id' => (int)$userId,
                'name' => $name,
                'email' => $email
            ]
        ], 201);
    }

    // POST /api/auth/login
    public static function login(): void {
        $data = \get_json_input();
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            \send_json('error', 'email and password are required', null, 400);
        }

        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT id, password_hash, name, email FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            \send_json('error', 'Invalid credentials', null, 401);
        }

        $token = \generate_token((int)$user['id']);
        \send_json('success', 'Login successful', [
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ]
        ]);
    }
}
