CREATE TABLE categorias_capacitaciones (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    tipo ENUM('area','subarea','carpeta') DEFAULT 'area',
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    padre_id INT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_padre_id (padre_id),

    CONSTRAINT fk_categoria_padre
        FOREIGN KEY (padre_id)
        REFERENCES categorias_capacitaciones(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias_gestion_humana (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    tipo ENUM('area','subarea','carpeta') DEFAULT 'area',
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    padre_id INT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    quiz_url VARCHAR(500) NULL,

    PRIMARY KEY (id),
    INDEX idx_padre_id (padre_id),

    CONSTRAINT fk_categoria_padre
        FOREIGN KEY (padre_id)
        REFERENCES categorias_capacitaciones(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documentos_capacitaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion VARCHAR(500),
    tipo_archivo ENUM('pdf','video','word','excel','link','imagen') DEFAULT 'pdf',
    url VARCHAR(500) NOT NULL,
    duracion_minutos INT,
    tamano_bytes BIGINT,
    vistas INT DEFAULT 0,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_por INT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE documentos_gestion_humana (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion VARCHAR(500),
    tipo_archivo ENUM('pdf','video','word','excel','link','imagen') DEFAULT 'pdf',
    url VARCHAR(500) NOT NULL,
    duracion_minutos INT,
    tamano_bytes BIGINT,
    vistas INT DEFAULT 0,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_por INT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE documentos_sig (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    archivo_url VARCHAR(500) NOT NULL,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_por INT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE logistica (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marca VARCHAR(50) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    archivo_url VARCHAR(500) NOT NULL,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_por INT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    ruta VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permisos_capacitaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    tipo ENUM('area','carpeta') DEFAULT 'area',
    puede_ver TINYINT(1) DEFAULT 1,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permisos_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    modulo_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE politicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT,
    archivo_url VARCHAR(500) NOT NULL,
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_por INT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE progreso_gestion_humana (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    documento_id INT NOT NULL,
    visto TINYINT(1) DEFAULT 1,
    fecha_visto DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE progreso_usuarios_capacitaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    documento_id INT NOT NULL,
    visto TINYINT(1) DEFAULT 1,
    fecha_visto DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE quiz_gestion_humana (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NOT NULL,
    completado TINYINT(1) DEFAULT 1,
    fecha_completado DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE slider (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255),
    imagen_url VARCHAR(500) NOT NULL,
    tipo ENUM('noticia','promocion','aviso','evento') DEFAULT 'noticia',
    enlace VARCHAR(500),
    orden INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    creado_por INT
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    usuario VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    perfil_usuario VARCHAR(100),
    cargo VARCHAR(100),
    sede VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    rol_id INT,
    activo TINYINT(1) DEFAULT 1,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME
);

CREATE TABLE usuarios_permisos_capacitaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP
);