CREATE DATABASE IF NOT EXISTS sistema_reuniones_inap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_reuniones_inap;
CREATE TABLE IF NOT EXISTS personas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cedula VARCHAR(20) NOT NULL UNIQUE,
 nombre VARCHAR(150) NOT NULL,
 sexo ENUM('F','M','Otro') NOT NULL DEFAULT 'Otro',
 cargo VARCHAR(120) NOT NULL,
 institucion VARCHAR(150) NOT NULL DEFAULT 'INAP',
 creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS reuniones (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(64) NOT NULL UNIQUE,
 actividad VARCHAR(180) NOT NULL,
 monitor VARCHAR(150) NOT NULL,
 lugar VARCHAR(180) NOT NULL,
 fecha DATE NOT NULL,
 hora TIME NOT NULL,
 duracion INT UNSIGNED NOT NULL,
 creada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS asistencias (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reunion_id INT UNSIGNED NOT NULL,
 persona_id INT UNSIGNED NOT NULL,
 firma LONGTEXT NOT NULL,
 registrado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_persona_reunion (reunion_id, persona_id),
 CONSTRAINT fk_asistencia_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones(id) ON DELETE CASCADE,
 CONSTRAINT fk_asistencia_persona FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
INSERT IGNORE INTO personas (cedula,nombre,sexo,cargo,institucion) VALUES
('001-0000000-1','Persona de Prueba','M','Analista','INAP'),
('001-0000000-2','Participante de Prueba','F','Asistente','INAP');
