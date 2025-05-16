-- drop database fixit;

SET NAMES 'utf8mb4';
SET CHARACTER SET utf8mb4;
SET collation_connection = 'utf8mb4_unicode_ci'; -- O 'utf8mb4_general_ci', dependiendo de tus necesidades de comparación y ordenamiento

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS fixit;

-- Usar la base de datos
USE fixit;

-- Tabla para almacenar información de los usuarios (sin cambios)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- *********************************************************
-- * NUEVA TABLA: incident_statuses                        *
-- * Almacena los posibles estados que puede tener una incidencia *
-- *********************************************************
CREATE TABLE incident_statuses (
    status_id INT AUTO_INCREMENT PRIMARY KEY,
    status_name VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- *********************************************************
-- * INSERTAR LOS ESTADOS INICIALES                        *
-- * Es CRUCIAL que 'Pendiente' se inserte aquí para poder *
-- * usar su ID como valor por defecto en la tabla incidents. *
-- *********************************************************
INSERT INTO incident_statuses (status_name) VALUES
('Pendiente'),
('En Proceso'),
('Solucionado'),
('Cerrado'),
('Re-abierto'),
('Cancelado');


-- *********************************************************
-- * TABLA: incidents                                    *
-- * Modificada para usar clave foránea y valor por defecto *
-- * funcional (requiere MySQL 8.0.13 o superior)          *
-- *********************************************************
CREATE TABLE incidents (
    incident_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100),
    affected_asset VARCHAR(255),

    requestor_id INT NOT NULL,
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Clave foránea para el estado.
    -- Usamos un VALOR POR DEFECTO FUNCIONAL que busca el ID de 'Pendiente'.
    -- Esto solo funciona si la tabla incident_statuses ya existe y contiene 'Pendiente'.
    -- (SELECT status_id FROM incident_statuses WHERE status_name = 'Pendiente'),
    status_id INT NOT NULL DEFAULT 1,
    

    in_progress_details TEXT NULL,

    assigned_to_id INT NULL,
    assigned_at TIMESTAMP NULL,

    resolved_at TIMESTAMP NULL,
    resolution_comments TEXT NULL,

    -- Claves foráneas
    CONSTRAINT fk_requestor
        FOREIGN KEY (requestor_id) REFERENCES users(user_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_assigned_to
        FOREIGN KEY (assigned_to_id) REFERENCES users(user_id)
        ON DELETE SET NULL,
    CONSTRAINT fk_incident_status -- Clave foránea para el estado
        FOREIGN KEY (status_id) REFERENCES incident_statuses(status_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- *********************************************************
-- * ÍNDICES                                             *
-- * Modificado el índice para usar status_id               *
-- *********************************************************
CREATE INDEX idx_incidents_status ON incidents (status_id);
CREATE INDEX idx_incidents_reported_at ON incidents (reported_at);
CREATE INDEX idx_incidents_requestor ON incidents (requestor_id);
CREATE INDEX idx_incidents_assigned_to ON incidents (assigned_to_id);

-- Insertar datos de ejemplo (usuarios)
INSERT INTO users (name, email)
VALUES ('Chuarchenager', 'chuarche@ejemplo.com');

-- Ejemplo de inserción que usa el valor por defecto para status_id:
INSERT INTO incidents (title, description, requestor_id, category)
VALUES ('Problema con el software VirtualBox', 'El programa se cierra inesperadamente.', 1, 'Software');
INSERT INTO incidents (title, description, requestor_id, category)
VALUES ('Problema con las horas del proyecto FCT', 'Me han tangando, quieren que haga dos millones de hora de mas!.', 1, 'Recursos humanos');
INSERT INTO incidents (title, description, requestor_id, category, status_id)
VALUES ('Configurar nuevo equipo', 'Necesito que configuren un PC nuevo para el usuario Pascual Ligero del departamento de festejos.', 1, 'Hardware', 2);


-- Ejemplo de inserción especificando otro estado (deberías obtener el ID del estado primero en tu código PHP):
-- SELECT status_id FROM incident_statuses WHERE status_name = 'En Proceso'; -- Digamos que devuelve 2


