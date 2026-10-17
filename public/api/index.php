<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Nucleo/Configuracion.php';
require_once __DIR__ . '/../../src/Nucleo/BaseDeDatos.php';

use Aplicacion\Nucleo\Configuracion;
use Aplicacion\Nucleo\BaseDeDatos;

date_default_timezone_set(Configuracion::obtener('timezone', 'America/Bogota'));

echo "<h1>Prueba de Conexión a la Base de Datos</h1>";

try {
    $db = BaseDeDatos::obtenerConexion();
    echo "<p style='color: green; font-weight: bold;'>✔ ¡Éxito! Clase BaseDeDatos y Configuracion cargadas correctamente.</p>";

} catch (\Throwable $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Error de conexión o configuración:</p>";
    echo "<pre style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb;'>" . $e->getMessage() . "</pre>";
}