CREATE DATABASE IF NOT EXISTS hecso2_db;
USE hecso2_db;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
);

INSERT INTO roles (nombre) VALUES ('Administrador'), ('Usuario'), ('Supervisor');

CREATE TABLE IF NOT EXISTS cargos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL
);

INSERT INTO cargos (nombre) VALUES ('Gerente'), ('Operario'), ('Chofer');

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- Insert admin user (password: admin123)
INSERT INTO usuarios (nombre, email, password, rol_id) VALUES 
('Admin', 'admin@hecso2.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

CREATE TABLE IF NOT EXISTS trabajadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    rut VARCHAR(20) NOT NULL,
    cargo_id INT,
    fecha_ingreso DATE,
    FOREIGN KEY (cargo_id) REFERENCES cargos(id)
);

CREATE TABLE IF NOT EXISTS vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patente VARCHAR(10) NOT NULL,
    marca VARCHAR(50),
    modelo VARCHAR(50),
    anio INT
);

CREATE TABLE IF NOT EXISTS mantenciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT,
    descripcion TEXT,
    fecha DATE,
    costo DECIMAL(10,2),
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
);

CREATE TABLE IF NOT EXISTS combustible (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT,
    litros DECIMAL(10,2),
    costo DECIMAL(10,2),
    fecha DATE,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id)
);

CREATE TABLE IF NOT EXISTS bitacora (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(255),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS certificados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    archivo VARCHAR(255),
    fecha_emision DATE,
    fecha_vencimiento DATE
);

CREATE TABLE IF NOT EXISTS calendario_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100),
    descripcion TEXT,
    start_date DATETIME,
    end_date DATETIME
);
