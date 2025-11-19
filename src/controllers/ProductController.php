<?php
// src/controllers/ProductController.php

namespace App\Controllers;

use App\Utils\Database;

class ProductController {
    public static function getAll() {
        $db = Database::getInstance()->getConnection();
        $result = $db->query("SELECT id, name, slug, description, price, stock, category, brand FROM products");
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $products]);
    }

    public static function getBySlug($slug) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, name, slug, description, price, stock, category, brand FROM products WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(["status" => "success", "data" => $row]);
        } else {
            echo json_encode(["status" => "error", "message" => "Product not found"]);
        }
    }
}
?>
