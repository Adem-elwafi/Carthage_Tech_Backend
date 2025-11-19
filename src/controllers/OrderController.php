<?php
// src/controllers/OrderController.php

namespace App\Controllers;

use App\Utils\Database;

class OrderController {
    // POST /api/orders/create (protected)
    public static function create(int $userId): void {
        $conn = Database::getConnection();
        $conn->begin_transaction();
        try {
            // Find open cart
            $stmt = $conn->prepare("SELECT id FROM carts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $cart = $res->fetch_assoc();
            $stmt->close();

            if (!$cart) { throw new \Exception('No open cart'); }
            $cartId = (int)$cart['id'];

            // Fetch items
            $sqlItems = 'SELECT ci.product_id, ci.quantity, ci.unit_price, p.stock FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?';
            $stmt = $conn->prepare($sqlItems);
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $res = $stmt->get_result();
            $items = [];
            $total = 0.0;
            while ($row = $res->fetch_assoc()) {
                if ((int)$row['stock'] < (int)$row['quantity']) {
                    throw new \Exception('Insufficient stock for one or more items');
                }
                $items[] = $row;
                $total += (float)$row['unit_price'] * (int)$row['quantity'];
            }
            $stmt->close();
            if (!$items) { throw new \Exception('Cart is empty'); }

            // Create order
            $stmt = $conn->prepare('INSERT INTO orders (user_id, total, status) VALUES (?,?,?)');
            $status = 'created';
            $stmt->bind_param('ids', $userId, $total, $status);
            $stmt->execute();
            $orderId = $stmt->insert_id;
            $stmt->close();

            // Insert order items and decrement stock
            $stmtItem = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)');
            $stmtStock = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($items as $it) {
                $pid = (int)$it['product_id'];
                $q = (int)$it['quantity'];
                $up = (float)$it['unit_price'];
                $stmtItem->bind_param('iiid', $orderId, $pid, $q, $up);
                $stmtItem->execute();
                $stmtStock->bind_param('ii', $q, $pid);
                $stmtStock->execute();
            }
            $stmtItem->close();
            $stmtStock->close();

            // Mark cart as ordered and clear items
            $conn->query("UPDATE carts SET status = 'ordered' WHERE id = " . (int)$cartId);
            $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            \send_json('success', 'Order created', [ 'order_id' => (int)$orderId, 'total' => $total ]);
        } catch (\Throwable $e) {
            $conn->rollback();
            \send_json('error', $e->getMessage(), null, 400);
        }
    }

    // GET /api/orders (protected)
    public static function list(int $userId): void {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT id, total, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $orders = [];
        while ($row = $res->fetch_assoc()) {
            $orders[] = [
                'id' => (int)$row['id'],
                'total' => (float)$row['total'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'items' => self::fetchOrderItems($conn, (int)$row['id'])
            ];
        }
        $stmt->close();
        \send_json('success', 'Orders fetched', $orders);
    }

    private static function fetchOrderItems($conn, int $orderId): array {
        $sql = 'SELECT oi.product_id, oi.quantity, oi.unit_price, p.name, p.slug FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id ASC';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'product_id' => (int)$row['product_id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'quantity' => (int)$row['quantity'],
                'unit_price' => (float)$row['unit_price'],
                'line_total' => (float)$row['unit_price'] * (int)$row['quantity']
            ];
        }
        $stmt->close();
        return $items;
    }
}
