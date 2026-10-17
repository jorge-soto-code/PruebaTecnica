DROP DATABASE IF EXISTS soporte_tickets;

CREATE DATABASE soporte_tickets
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE soporte_tickets;

-- =====================================================================
-- 1. USUARIOS: las personas internas que piden soporte
-- =====================================================================
CREATE TABLE usuarios (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo          VARCHAR(20)  NOT NULL,
    nombre          VARCHAR(120) NOT NULL,
    cargo           VARCHAR(100) NOT NULL,
    area            VARCHAR(100) NOT NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    -- No puede haber dos usuarios con el mismo código (y la búsqueda es rápida)
    UNIQUE KEY unico_usuarios_codigo (codigo),
    -- Para el filtro de texto libre por nombre
    KEY indice_usuarios_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. TECNICOS: quienes atienden los tickets
-- =====================================================================
CREATE TABLE tecnicos (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(120) NOT NULL,
    correo          VARCHAR(150) NOT NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    fecha_creacion  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY unico_tecnicos_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. CATEGORIAS: Hardware / Software / Red / Otro
--    (en una tabla porque la prueba pide que no estén escritas en el HTML)
-- =====================================================================
CREATE TABLE categorias (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(50)  NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY unico_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. PRIORIDADES: Alta / Media / Baja y sus horas de SLA
--    Las horas se guardan aquí para no escribirlas fijas en el código.
-- =====================================================================
CREATE TABLE prioridades (
    id          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(20)       NOT NULL,
    horas_sla   SMALLINT UNSIGNED NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY unico_prioridades_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. CONSECUTIVOS: contador de números de ticket, una fila por AÑO
--
--    Sirve para que dos usuarios que registran al mismo tiempo nunca
--    reciban el mismo número: al crear un ticket, el programa bloquea la
--    fila del año (SELECT ... FOR UPDATE) dentro de una transacción,
--    suma 1 y guarda. Como hay una fila por año, reinicia cada año.
-- =====================================================================
CREATE TABLE consecutivos (
    anio           SMALLINT UNSIGNED NOT NULL,
    ultimo_numero  INT UNSIGNED      NOT NULL DEFAULT 0,

    PRIMARY KEY (anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. TICKETS: la tabla principal
-- =====================================================================
CREATE TABLE tickets (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    numero             VARCHAR(15)   NOT NULL,
    usuario_id         INT UNSIGNED  NOT NULL,
    categoria_id       INT UNSIGNED  NOT NULL,
    prioridad_id       INT UNSIGNED  NOT NULL,
    tecnico_id         INT UNSIGNED  NOT NULL,
    estado             ENUM('ABIERTO','EN CURSO','CERRADO') NOT NULL DEFAULT 'ABIERTO',
    descripcion        VARCHAR(1000) NOT NULL,
    solucion           TEXT          NULL,
    fecha_creacion     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_limite       DATETIME      NOT NULL,
    fecha_cierre       DATETIME      NULL,

    PRIMARY KEY (id),

    -- Nunca puede haber dos tickets con el mismo número
    UNIQUE KEY unico_tickets_numero (numero),

    -- Un índice por cada filtro de la tabla (estado, prioridad, técnico)
    KEY indice_tickets_estado    (estado),
    KEY indice_tickets_prioridad (prioridad_id),
    KEY indice_tickets_tecnico   (tecnico_id),

    -- Llaves foráneas: no se puede crear un ticket con un usuario, categoría,
    -- prioridad o técnico que no existe
    CONSTRAINT fk_tickets_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id),
    CONSTRAINT fk_tickets_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id),
    CONSTRAINT fk_tickets_prioridad FOREIGN KEY (prioridad_id) REFERENCES prioridades (id),
    CONSTRAINT fk_tickets_tecnico   FOREIGN KEY (tecnico_id)   REFERENCES tecnicos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. TICKET_HISTORIAL: registro de cada cambio de un ticket
--    Una fila por: creación, cambio de estado o reasignación de técnico.
--    Solo se inserta, nunca se edita ni se borra.
-- =====================================================================
CREATE TABLE ticket_historial (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    ticket_id        INT UNSIGNED  NOT NULL,
    estado_anterior  ENUM('ABIERTO','EN CURSO','CERRADO') NULL,
    estado_nuevo     ENUM('ABIERTO','EN CURSO','CERRADO') NOT NULL,
    observacion      VARCHAR(1000) NULL,
    fecha_creacion   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_historial_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;