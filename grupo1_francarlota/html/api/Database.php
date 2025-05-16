<?php

require_once __DIR__ . '/config.php';

class Database {
    private $connection;

    public function connect() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

        if ($this->connection->connect_error) {
            sendError("Database Connection failed: " . $this->connection->connect_error, 500);
        }

        return $this->connection;
    }
}
?>
