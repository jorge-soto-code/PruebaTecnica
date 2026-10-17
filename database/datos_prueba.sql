-- =====================================================================
-- DATOS DE PRUEBA: soporte_tickets
--
-- QUÉ HACE:
--   Carga los datos de prueba. Ejecútalo DESPUÉS de esquema.sql y en una
--   sola corrida (el script usa una variable, @ahora, que vive solo
--   durante esa ejecución).
--
-- QUÉ CARGA:
--   - 4 categorías y 3 prioridades (con su SLA)
--   - 10 usuarios (USR010 está INACTIVO)
--   - 4 técnicos (Luisa Patiño está INACTIVA)
--   - 25 tickets en distintos estados
--   - El historial de cada ticket
--   - El contador de consecutivos del año 2026 (queda en 25)
--
-- DETALLE IMPORTANTE — FECHAS RELATIVAS:
--   Las fechas de los tickets se calculan "hace X minutos" respecto al
--   momento en que ejecutas el script (@ahora). Así, SIEMPRE habrá tickets
--   vencidos, por vencer y en tiempo, sin importar el día que lo ejecutes
--   (por ejemplo, el día de la sustentación).
--
-- LO QUE VERÁS DESPUÉS DE EJECUTARLO (útil para la demo del video):
--   Estados:  14 CERRADO, 5 EN CURSO, 6 ABIERTO.
--   Vencidos (no cerrados): TK-...011, 015 y 018.
--   Por vencer:             TK-...013, 019 y 022.
--   Carga activa:           Jhon 4/5, Natalia 5/5 (AL TOPE), Esteban 2/5.
--     -> Natalia sirve para demostrar el error 409 "técnico al tope".
-- =====================================================================

USE soporte_tickets;
SET NAMES utf8mb4;

-- "Ahora": punto de referencia para calcular todas las fechas.
SET @ahora = NOW();


-- =====================================================================
-- CATÁLOGOS
-- =====================================================================
INSERT INTO categorias (id, nombre) VALUES
  (1, 'Hardware'),
  (2, 'Software'),
  (3, 'Red'),
  (4, 'Otro');

-- SLA: Alta 4 horas, Media 24 horas, Baja 72 horas.
INSERT INTO prioridades (id, nombre, horas_sla) VALUES
  (1, 'Alta',  4),
  (2, 'Media', 24),
  (3, 'Baja',  72);


-- =====================================================================
-- USUARIOS (10; el último está inactivo para probar el bloqueo)
-- =====================================================================
INSERT INTO usuarios (id, codigo, nombre, cargo, area, activo) VALUES
  (1,  'USR001', 'Carlos Andrés Gómez Ruiz',     'Analista Contable',             'Contabilidad',   1),
  (2,  'USR002', 'María Fernanda López Pérez',   'Asistente Administrativa',      'Administración', 1),
  (3,  'USR003', 'Juan Pablo Ramírez Torres',    'Coordinador de Ventas',         'Comercial',      1),
  (4,  'USR004', 'Laura Valentina Castro Díaz',  'Ejecutiva Comercial',           'Comercial',      1),
  (5,  'USR005', 'Andrés Felipe Moreno Salazar', 'Analista de Compras',           'Compras',        1),
  (6,  'USR006', 'Daniela Restrepo Henao',       'Profesional de Talento Humano', 'Talento Humano', 1),
  (7,  'USR007', 'Sebastián Ospina Vélez',       'Jefe de Logística',             'Logística',      1),
  (8,  'USR008', 'Camila Andrea Jaramillo Arias','Auxiliar de Nómina',            'Talento Humano', 1),
  (9,  'USR009', 'Santiago Herrera Mejía',       'Analista de Calidad',           'Calidad',        1),
  (10, 'USR010', 'Paola Andrea Quintero Gil',    'Auxiliar de Archivo',           'Administración', 0);  -- INACTIVO


-- =====================================================================
-- TÉCNICOS (4; el último está inactivo)
-- =====================================================================
INSERT INTO tecnicos (id, nombre, correo, activo) VALUES
  (1, 'Jhon Alexander Muñoz',   'jhon.munoz@empresa.local',     1),
  (2, 'Natalia Cardona Rojas',  'natalia.cardona@empresa.local', 1),
  (3, 'Esteban Vargas Giraldo', 'esteban.vargas@empresa.local', 1),
  (4, 'Luisa Fernanda Patiño',  'luisa.patino@empresa.local',   0);  -- INACTIVA (solo tickets históricos)


-- =====================================================================
-- TICKETS (25)
--
-- Columnas: id, numero, usuario_id, categoria_id, prioridad_id, tecnico_id,
--           estado, descripcion, solucion, fecha_creacion, fecha_limite, fecha_cierre
--
-- Claves de lectura:
--   categoria: 1 Hardware | 2 Software | 3 Red | 4 Otro
--   prioridad: 1 Alta (4h) | 2 Media (24h) | 3 Baja (72h)
--   fecha_creacion = @ahora menos N minutos.
--   fecha_limite   = fecha_creacion + las horas del SLA de su prioridad
--                    (Alta 4, Media 24, Baja 72).
-- =====================================================================
INSERT INTO tickets
  (id, numero, usuario_id, categoria_id, prioridad_id, tecnico_id, estado,
   descripcion, solucion, fecha_creacion, fecha_limite, fecha_cierre)
VALUES
-- ---- 14 tickets CERRADOS (historia) ----
(1,  'TK-2026-000001', 1, 1, 2, 1, 'CERRADO',
  'El computador de mi puesto no enciende desde esta mañana, ya revisé el cable de poder.',
  'Se reemplazó la fuente de poder y se verificó el encendido del equipo.',
  DATE_SUB(@ahora, INTERVAL 20160 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 20160 MINUTE), INTERVAL 24 HOUR), DATE_SUB(@ahora, INTERVAL 18960 MINUTE)),

(2,  'TK-2026-000002', 2, 2, 3, 2, 'CERRADO',
  'Necesito instalar un programa para firmar documentos PDF del área administrativa.',
  'Se instaló el editor de PDF autorizado y se configuró la firma digital.',
  DATE_SUB(@ahora, INTERVAL 19000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 19000 MINUTE), INTERVAL 72 HOUR), DATE_SUB(@ahora, INTERVAL 16120 MINUTE)),

-- Este se cerró TARDE (5 horas, el SLA de Alta es 4): sirve para mostrar que cerrado no tiene indicador.
(3,  'TK-2026-000003', 3, 3, 1, 3, 'CERRADO',
  'No tengo internet en la oficina de ventas, ninguno del equipo puede navegar.',
  'Se reinició el switch del piso y se reemplazó un cable de red dañado.',
  DATE_SUB(@ahora, INTERVAL 17500 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 17500 MINUTE), INTERVAL 4 HOUR), DATE_SUB(@ahora, INTERVAL 17200 MINUTE)),

(4,  'TK-2026-000004', 4, 4, 3, 1, 'CERRADO',
  'Solicito el cambio de contraseña del correo corporativo porque la olvidé.',
  'Se restableció la contraseña y se pidió cambiarla en el primer ingreso.',
  DATE_SUB(@ahora, INTERVAL 15000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 15000 MINUTE), INTERVAL 72 HOUR), DATE_SUB(@ahora, INTERVAL 13500 MINUTE)),

(5,  'TK-2026-000005', 5, 2, 2, 2, 'CERRADO',
  'El sistema de compras muestra un error al generar órdenes de compra nuevas.',
  'Se limpió la caché y se actualizó el cliente del sistema de compras.',
  DATE_SUB(@ahora, INTERVAL 14000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 14000 MINUTE), INTERVAL 24 HOUR), DATE_SUB(@ahora, INTERVAL 12000 MINUTE)),

(6,  'TK-2026-000006', 6, 1, 3, 3, 'CERRADO',
  'El mouse y el teclado dejaron de responder de forma intermitente en mi equipo.',
  'Se cambiaron el mouse y el teclado por unos nuevos.',
  DATE_SUB(@ahora, INTERVAL 12000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 12000 MINUTE), INTERVAL 72 HOUR), DATE_SUB(@ahora, INTERVAL 9000 MINUTE)),

(7,  'TK-2026-000007', 7, 3, 2, 1, 'CERRADO',
  'La impresora de logística no aparece en la red y no puedo imprimir las guías.',
  'Se reasignó la dirección IP de la impresora y se reinstaló el controlador.',
  DATE_SUB(@ahora, INTERVAL 10000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 10000 MINUTE), INTERVAL 24 HOUR), DATE_SUB(@ahora, INTERVAL 9100 MINUTE)),

(8,  'TK-2026-000008', 8, 2, 1, 2, 'CERRADO',
  'No puedo abrir el programa de nómina, sale un error y hoy es día de pago.',
  'Se reparó la instalación del programa de nómina y se restauró el acceso.',
  DATE_SUB(@ahora, INTERVAL 8500 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 8500 MINUTE), INTERVAL 4 HOUR), DATE_SUB(@ahora, INTERVAL 8300 MINUTE)),

(9,  'TK-2026-000009', 9, 4, 2, 3, 'CERRADO',
  'Necesito acceso a la carpeta compartida de Calidad para consultar procedimientos.',
  'Se otorgaron los permisos de lectura a la carpeta compartida de Calidad.',
  DATE_SUB(@ahora, INTERVAL 7000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 7000 MINUTE), INTERVAL 24 HOUR), DATE_SUB(@ahora, INTERVAL 6000 MINUTE)),

-- Atendido por Luisa (técnica inactiva hoy): muestra que lo histórico se conserva.
(10, 'TK-2026-000010', 1, 2, 3, 4, 'CERRADO',
  'Excel se cierra solo cuando abro el archivo de conciliaciones del mes.',
  'Se reparó la instalación de Office y se recuperó el archivo.',
  DATE_SUB(@ahora, INTERVAL 5500 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 5500 MINUTE), INTERVAL 72 HOUR), DATE_SUB(@ahora, INTERVAL 3000 MINUTE)),

(12, 'TK-2026-000012', 2, 1, 2, 4, 'CERRADO',
  'La pantalla del portátil parpadea y se ven líneas horizontales todo el día.',
  'Se cambió el cable de video interno y se probó la pantalla.',
  DATE_SUB(@ahora, INTERVAL 4000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 4000 MINUTE), INTERVAL 24 HOUR), DATE_SUB(@ahora, INTERVAL 2700 MINUTE)),

(14, 'TK-2026-000014', 4, 3, 3, 3, 'CERRADO',
  'El WiFi de la sala de juntas es muy lento cuando hay reuniones con clientes.',
  'Se cambió el canal del punto de acceso y se actualizó su firmware.',
  DATE_SUB(@ahora, INTERVAL 3000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 3000 MINUTE), INTERVAL 72 HOUR), DATE_SUB(@ahora, INTERVAL 1200 MINUTE)),

(16, 'TK-2026-000016', 5, 4, 1, 1, 'CERRADO',
  'Perdí el acceso a mi usuario del dominio y no puedo iniciar sesión en el equipo.',
  'Se desbloqueó el usuario del dominio y se restableció la contraseña.',
  DATE_SUB(@ahora, INTERVAL 2000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 2000 MINUTE), INTERVAL 4 HOUR), DATE_SUB(@ahora, INTERVAL 1820 MINUTE)),

(17, 'TK-2026-000017', 6, 2, 2, 2, 'CERRADO',
  'Necesito que instalen el antivirus corporativo en mi equipo nuevo de trabajo.',
  'Se instaló y activó el antivirus corporativo con las políticas vigentes.',
  DATE_SUB(@ahora, INTERVAL 1800 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 1800 MINUTE), INTERVAL 24 HOUR), DATE_SUB(@ahora, INTERVAL 1200 MINUTE)),

-- ---- 11 tickets ACTIVOS (ABIERTO / EN CURSO) ----
-- 83 horas y es Baja (72h): VENCIDO. Técnica: Natalia.
(11, 'TK-2026-000011', 7, 3, 3, 2, 'EN CURSO',
  'El punto de red del puesto de la bodega no funciona, hay que revisar el cableado.',
  NULL, DATE_SUB(@ahora, INTERVAL 5000 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 5000 MINUTE), INTERVAL 72 HOUR), NULL),

-- 58 horas y es Baja (72h): POR VENCER (queda menos del 25%). Técnico: Jhon.
(13, 'TK-2026-000013', 3, 2, 3, 1, 'EN CURSO',
  'Necesito la actualización del programa de cotizaciones para mi equipo de ventas.',
  NULL, DATE_SUB(@ahora, INTERVAL 3500 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 3500 MINUTE), INTERVAL 72 HOUR), NULL),

-- 41 horas y es Media (24h): VENCIDO. Sigue ABIERTO (nadie lo ha tomado).
(15, 'TK-2026-000015', 9, 4, 2, 1, 'ABIERTO',
  'Solicito la creación de un correo compartido para el área de Calidad.',
  NULL, DATE_SUB(@ahora, INTERVAL 2500 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 2500 MINUTE), INTERVAL 24 HOUR), NULL),

-- 25 horas y es Alta (4h): VENCIDO. Fue reasignado de Esteban a Jhon (ver historial).
(18, 'TK-2026-000018', 8, 3, 1, 1, 'EN CURSO',
  'Sin conexión a la red en nómina, no puedo subir el archivo del banco hoy.',
  NULL, DATE_SUB(@ahora, INTERVAL 1500 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 1500 MINUTE), INTERVAL 4 HOUR), NULL),

-- 18 horas y es Media (24h): POR VENCER.
(19, 'TK-2026-000019', 2, 1, 2, 2, 'EN CURSO',
  'El portátil se sobrecalienta y se apaga solo cuando uso varios programas.',
  NULL, DATE_SUB(@ahora, INTERVAL 1100 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 1100 MINUTE), INTERVAL 24 HOUR), NULL),

-- 13 horas y es Baja (72h): EN TIEMPO.
(20, 'TK-2026-000020', 6, 2, 3, 2, 'ABIERTO',
  'Quiero que actualicen el programa de presentaciones a la versión más reciente.',
  NULL, DATE_SUB(@ahora, INTERVAL 800 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 800 MINUTE), INTERVAL 72 HOUR), NULL),

-- 5 horas y es Media (24h): EN TIEMPO.
(21, 'TK-2026-000021', 5, 3, 2, 3, 'ABIERTO',
  'La VPN no conecta desde casa y necesito revisar órdenes de compra pendientes.',
  NULL, DATE_SUB(@ahora, INTERVAL 300 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 300 MINUTE), INTERVAL 24 HOUR), NULL),

-- 3 h 35 min y es Alta (4h): POR VENCER.
(22, 'TK-2026-000022', 1, 1, 1, 2, 'ABIERTO',
  'La impresora de contabilidad atasca el papel y hoy hay cierre contable.',
  NULL, DATE_SUB(@ahora, INTERVAL 215 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 215 MINUTE), INTERVAL 4 HOUR), NULL),

-- 1 h 40 min y es Alta (4h): EN TIEMPO.
(23, 'TK-2026-000023', 3, 2, 1, 2, 'EN CURSO',
  'El CRM de ventas no carga la lista de clientes y tengo una reunión en una hora.',
  NULL, DATE_SUB(@ahora, INTERVAL 100 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 100 MINUTE), INTERVAL 4 HOUR), NULL),

-- 45 minutos y es Media (24h): EN TIEMPO.
(24, 'TK-2026-000024', 7, 2, 2, 3, 'ABIERTO',
  'Solicito instalar el programa de rutas de entrega en el equipo de la bodega.',
  NULL, DATE_SUB(@ahora, INTERVAL 45 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 45 MINUTE), INTERVAL 24 HOUR), NULL),

-- 20 minutos y es Alta (4h): EN TIEMPO.
(25, 'TK-2026-000025', 4, 4, 1, 1, 'ABIERTO',
  'Se me venció la contraseña del sistema de ventas y no puedo cotizar hoy.',
  NULL, DATE_SUB(@ahora, INTERVAL 20 MINUTE), DATE_ADD(DATE_SUB(@ahora, INTERVAL 20 MINUTE), INTERVAL 4 HOUR), NULL);



-- =====================================================================
-- CONSECUTIVOS: ya se usaron 25 números en 2026.
-- El próximo ticket que se cree será TK-2026-000026.
-- =====================================================================
INSERT INTO consecutivos (anio, ultimo_numero) VALUES (2026, 25);


-- =====================================================================
-- HISTORIAL
-- Se genera a partir de los tickets para no escribir 50 filas a mano.
--   Todo ticket tiene una fila de creación (estado anterior vacío).
--   Los EN CURSO y CERRADO tienen además el paso ABIERTO -> EN CURSO.
--   Los CERRADO tienen además el paso EN CURSO -> CERRADO (observación = solución).
--   Una reasignación se guarda con el mismo estado antes y después, y la
--   observación dice de qué técnico a qué técnico se movió.
-- =====================================================================

-- 1) Creación (todos los tickets)
INSERT INTO ticket_historial
  (ticket_id, estado_anterior, estado_nuevo, observacion, fecha_creacion)
SELECT id, NULL, 'ABIERTO', 'Ticket registrado', fecha_creacion
FROM tickets;

-- 2) ABIERTO -> EN CURSO (15 minutos después de crearse)
INSERT INTO ticket_historial
  (ticket_id, estado_anterior, estado_nuevo, observacion, fecha_creacion)
SELECT id, 'ABIERTO', 'EN CURSO', 'El técnico inicia la atención', DATE_ADD(fecha_creacion, INTERVAL 15 MINUTE)
FROM tickets
WHERE estado IN ('EN CURSO', 'CERRADO');

-- 3) EN CURSO -> CERRADO (en la fecha de cierre; la observación es la solución)
INSERT INTO ticket_historial
  (ticket_id, estado_anterior, estado_nuevo, observacion, fecha_creacion)
SELECT id, 'EN CURSO', 'CERRADO', solucion, fecha_cierre
FROM tickets
WHERE estado = 'CERRADO';

-- 4) Una reasignación de ejemplo: el ticket 18 nació con Esteban y pasó a Jhon.
INSERT INTO ticket_historial
  (ticket_id, estado_anterior, estado_nuevo, observacion, fecha_creacion)
SELECT id, 'ABIERTO', 'ABIERTO',
       'Reasignado de Esteban Vargas Giraldo a Jhon Alexander Muñoz para balancear la carga',
       DATE_ADD(fecha_creacion, INTERVAL 5 MINUTE)
FROM tickets
WHERE id = 18;
