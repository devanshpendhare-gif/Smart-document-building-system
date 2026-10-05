<?php

function ensure_database(): void
{
    $connection = new mysqli('localhost', 'root', '');

    if ($connection->connect_error) {
        throw new RuntimeException('Database connection failed: ' . $connection->connect_error);
    }

    $connection->query("CREATE DATABASE IF NOT EXISTS docindia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connection->close();
}

function get_db_connection(): mysqli
{
    static $connection = null;

    if ($connection === null) {
        $connection = new mysqli('localhost', 'root', '', 'docindia');

        if ($connection->connect_error) {
            throw new RuntimeException('Database connection failed: ' . $connection->connect_error);
        }

        $connection->set_charset('utf8mb4');
    }

    return $connection;
}

function ensure_users_table(): void
{
    $connection = get_db_connection();

    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$connection->query($sql)) {
        throw new RuntimeException('Failed to create users table: ' . $connection->error);
    }

    $reset_sql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL UNIQUE,
        otp_code VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$connection->query($reset_sql)) {
        throw new RuntimeException('Failed to create password reset table: ' . $connection->error);
    }
}
