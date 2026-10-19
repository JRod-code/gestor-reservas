<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

/**
 * Clase encargada de la configuración y gestión de la conexión a la base de datos.
 */
class Database {
    private string $host = "localhost";
    private string $db_name = "reservas_db";
    private string $username = "root";
    private string $password = "";
    private ?PDO $conn = null;

    public function getConnection(): ?PDO {
        
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva la emulación: Seguridad contra inyección SQL
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
           
        } catch (PDOException $exception) {
            error_log("Fallo crítico de conexión: " . $exception->getMessage());
            return null;
        }

        return $this->conn;
    }
}