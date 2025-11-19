<?php
// src/controllers/CartController.php

namespace App\Controllers;

use App\Utils\Database;

class CartController {
    // POST /api/cart/add (protected)
    public static function add(int $userId): void {
        $data = \get_json_input();
        $productId = (int)($data['product_id'] ?? 0);
        $qty = max(1, (int)($data['quantity'] ?? 1));
        if ($productId <= 0) { \send_json('error', 'product_id is required', null, 400); }

        $conn = Database::getConnection();
        $conn->begin_transaction();
        try {
            // Ensure open cart
            $cartId = self::getOrCreateOpenCartId($conn, $userId);

            // Fetch product and price
            $stmt = $conn->prepare('SELECT id, price, stock FROM products WHERE id = ?');
            $stmt->bind_param('i', $productId);
            $stmt->execute();
            $res = $stmt->get_result();
            $product = $res->fetch_assoc();
            $stmt->close();
            if (!$product) { throw new \Exception('Product not found'); }
            if ((int)$product['stock'] < $qty) { throw new \Exception('Insufficient stock'); }

            $unitPrice = (float)$product['price'];

            // Upsert cart item
            $stmt = $conn->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?');
            $stmt->bind_param('ii', $cartId, $productId);
            $stmt->execute();
            $res = $stmt->get_result();
            $existing = $res->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $newQty = (int)$existing['quantity'] + $qty;
                $stmt = $conn->prepare('UPDATE cart_items SET quantity = ?, unit_price = ? WHERE id = ?');
                $stmt->bind_param('idi', $newQty, $unitPrice, $existing['id']);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, unit_price) VALUES (?,?,?,?)');
                $stmt->bind_param('iiid', $cartId, $productId, $qty, $unitPrice);
                $stmt->execute();
                $stmt->close();
            }

            $items = self::fetchCartItems($conn, $cartId);
            $conn->commit();
            \send_json('success', 'Item added to cart', [ 'cart_id' => (int)$cartId, 'items' => $items ]);
        } catch (\Throwable $e) {
            $conn->rollback();
            \send_json('error', $e->getMessage(), null, 400);
        }
    }

    // GET /api/cart (protected)
    public static function list(int $userId): void {
        $conn = Database::getConnection();
        $cartId = self::getOpenCartId($conn, $userId);
        if (!$cartId) { \send_json('success', 'Cart fetched', ['cart_id' => null, 'items' => []]); }
        $items = self::fetchCartItems($conn, $cartId);
        \send_json('success', 'Cart fetched', ['cart_id' => (int)$cartId, 'items' => $items]);
    }

    private static function getOpenCartId($conn, int $userId): ?int {
        $stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }

    private static function getOrCreateOpenCartId($conn, int $userId): int {
        $cartId = self::getOpenCartId($conn, $userId);
        if ($cartId) return $cartId;
        $stmt = $conn->prepare('INSERT INTO carts (user_id, status) VALUES (?, "open")');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        return (int)$newId;
    }

    private static function fetchCartItems($conn, int $cartId): array {
        $sql = 'SELECT ci.id, ci.product_id, ci.quantity, ci.unit_price, p.name, p.slug, p.stock
                FROM cart_items ci
                JOIN products p ON p.id = ci.product_id
                WHERE ci.cart_id = ?
                ORDER BY ci.id DESC';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $cartId);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'id' => (int)$row['id'],
                'product_id' => (int)$row['product_id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'quantity' => (int)$row['quantity'],
                'unit_price' => (float)$row['unit_price'],
                'stock' => (int)$row['stock'],
                'line_total' => (float)$row['unit_price'] * (int)$row['quantity']
            ];
        }
        $stmt->close();
        return $items;
    }
}
