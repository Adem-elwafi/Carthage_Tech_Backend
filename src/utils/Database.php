<?php
// src/utils/Database.php

namespace App\Utils;

class Database {
    private static $instance = null;
    private $conn;

    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $db   = "carthage_tech";

    private function __construct() {
        $this->conn = new \mysqli($this->host, $this->user, $this->pass, $this->db);
        if ($this->conn->connect_error) {
            die("Database connection failed: " . $this->conn->connect_error);
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public static function getConnection() {
        return self::getInstance()->conn;
    }
}
?>
