<?php
declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

/**
 * Clase encargada de la configuración y gestión de la conexión a la base de datos.
 */
class Database {
    private ?PDO $conn = null;

    public function getConnection(): ?PDO {
        
        if ($this->conn !== null) {
            return $this->conn;
        }

        // Lectura segura del entorno con valores locales por defecto
        $host = getenv('DB_HOST') ?: "localhost";
        $db_name = getenv('DB_NAME') ?: "reservas_db";
        $username = getenv('DB_USER') ?: "root";
        $password = getenv('DB_PASS') ?: "";
        $port = getenv('DB_PORT') ?: "3306";

        try {
            $dsn = "mysql:host=" . $host . ";dbname=" . $db_name . ";port=" . $port . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Desactiva la emulación: Seguridad contra inyección SQL
            ];

            $this->conn = new PDO($dsn, $username, $password, $options);
           
        } catch (PDOException $exception) {
            error_log("Fallo crítico de conexión: " . $exception->getMessage());
            return null;
        }

        return $this->conn;
    }
}