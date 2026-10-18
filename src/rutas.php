<?php
/**
 * ARCHIVO: src/rutas.php
 * CAPA: Rutas
 *
 * QUÉ ES:
 *   La lista de TODAS las rutas de la API: qué dirección y método atiende
 *   cada controlador. Se lee de un vistazo y se va llenando fase por fase.
 *   Las rutas aquí NO llevan "/api": la Peticion ya la quita.
 *
 * ESTADO ACTUAL (Fase 2): todavía no hay controladores, así que cualquier
 * ruta responde 404. Eso sirve para probar la clave de la API y el formato
 * de error. Cuando se programe cada controlador se activa su línea.
 */
declare(strict_types=1); // Activa la verificación estricta de tipos

use Aplicacion\Nucleo\Enrutador; // Para indicar el tipo de la variable que recibe la función

return static function (Enrutador $enrutador): void { // Devuelve una función que registra las rutas en el enrutador que reciba
    // ---- Fase 3: lectura ----
    // $enrutador->registrarRutaGet('/usuarios/{codigo}', [new ControladorUsuarios(), 'mostrarUsuarioPorCodigo']);
    // $enrutador->registrarRutaGet('/tecnicos',          [new ControladorTecnicos(), 'listarTecnicosActivosConCarga']);
    // $enrutador->registrarRutaGet('/categorias',        [new ControladorCatalogos(), 'listarCategoriasYPrioridades']);
    // $enrutador->registrarRutaGet('/resumen',           [new ControladorCatalogos(), 'obtenerResumenDelTablero']);
    // $enrutador->registrarRutaGet('/tickets',           [new ControladorTickets(), 'listarTickets']);
    // $enrutador->registrarRutaGet('/tickets/{numero}',  [new ControladorTickets(), 'obtenerDetalleDelTicket']);

    // ---- Fase 4: crear ----
    // $enrutador->registrarRutaPost('/tickets',          [new ControladorTickets(), 'crearTicket']);

    // ---- Fase 5: cambios ----
    // $enrutador->registrarRutaPatch('/tickets/{numero}/estado',  [new ControladorTickets(), 'cambiarEstadoDelTicket']);
    // $enrutador->registrarRutaPatch('/tickets/{numero}/tecnico', [new ControladorTickets(), 'reasignarTecnicoDelTicket']);
};
