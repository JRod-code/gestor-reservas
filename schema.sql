CREATE DATABASE IF NOT EXISTS reservas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reservas_db;

CREATE TABLE IF NOT EXISTS reserva (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_huesped VARCHAR(100) NOT NULL,
    email_huesped VARCHAR(100) NOT NULL,
    nombre_alojamiento VARCHAR(150) NOT NULL,
    fecha_entrada DATE NOT NULL,
    fecha_salida DATE NOT NULL,
    estado ENUM('pendiente', 'confirmada', 'cancelada') DEFAULT 'pendiente',
    importe DECIMAL(10,2) NOT NULL,
    notas TEXT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_fechas (fecha_entrada, fecha_salida)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reserva_evento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reserva_id) REFERENCES reserva(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO reserva (nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, estado, importe, notas) VALUES
('Alejandro García', 'alejandro.garcia@email.com', 'Hotel Rural Central - Hab 102', '2026-11-01', '2026-11-05', 'confirmada', 450.00, 'Solicita habitación planta baja.'),
('María López', 'maria.lopez@email.com', 'Apartamento Centro Histórico', '2026-12-15', '2026-12-22', 'pendiente', 720.50, 'Viaja con un bebé. Requiere cuna.'),
('Carlos Mendoza', 'carlos.mendoza@email.com', 'Bungalow Vistas de la Montaña', '2026-10-20', '2026-10-22', 'cancelada', 180.00, 'Cancelación notificada vía telefónica.');

INSERT INTO reserva_evento (reserva_id, tipo, descripcion) VALUES
(1, 'CREACION', 'Reserva inicial registrada en estado pendiente.'),
(1, 'ESTADO_CAMBIO', 'La reserva cambió al estado: CONFIRMADA.'),
(2, 'CREACION', 'Reserva inicial registrada en estado pendiente.'),
(3, 'CREACION', 'Reserva inicial registrada en estado pendiente.'),
(3, 'ESTADO_CAMBIO', 'La reserva cambió al estado: CANCELADA.');
