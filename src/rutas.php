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
 * ESTADO ACTUAL (Fase 3): ya responden las rutas de lectura (GET). Las de
 * crear y cambiar (Fases 4 y 5) siguen comentadas hasta programarlas.
 */
declare(strict_types=1); // Activa la verificación estricta de tipos

use Aplicacion\Controladores\ControladorCatalogos; // Categorías, prioridades y resumen
use Aplicacion\Controladores\ControladorTecnicos; // Técnicos con su carga
use Aplicacion\Controladores\ControladorTickets; // Lista y detalle de tickets
use Aplicacion\Controladores\ControladorUsuarios; // Datos del solicitante
use Aplicacion\Nucleo\Enrutador; // Para indicar el tipo de la variable que recibe la función

return static function (Enrutador $enrutador): void { // Devuelve una función que registra las rutas en el enrutador que reciba
    // ---- Fase 3: lectura ----
    $enrutador->registrarRutaGet('/usuarios/{codigo}', [new ControladorUsuarios(), 'mostrarUsuarioPorCodigo']); // Datos del solicitante (200 o 404)
    $enrutador->registrarRutaGet('/tecnicos',          [new ControladorTecnicos(), 'listarTecnicosActivosConCarga']); // Técnicos activos con su carga
    $enrutador->registrarRutaGet('/categorias',        [new ControladorCatalogos(), 'listarCategoriasYPrioridades']); // Categorías y prioridades para las listas
    $enrutador->registrarRutaGet('/resumen',           [new ControladorCatalogos(), 'obtenerResumenDelTablero']); // Números del tablero
    $enrutador->registrarRutaGet('/tickets',           [new ControladorTickets(), 'listarTickets']); // Lista paginada con filtros
    $enrutador->registrarRutaGet('/tickets/{numero}',  [new ControladorTickets(), 'obtenerDetalleDelTicket']); // Detalle con historial (200 o 404)

    // ---- Fase 4: crear ----
    $enrutador->registrarRutaPost('/tickets',          [new ControladorTickets(), 'crearTicket']); // Crea un ticket (201, 409 o 422)

    // ---- Fase 5: cambios ----
    $enrutador->registrarRutaPatch('/tickets/{numero}/estado',  [new ControladorTickets(), 'cambiarEstadoDelTicket']); // Cambia el estado (200, 404, 409 o 422)
    $enrutador->registrarRutaPatch('/tickets/{numero}/tecnico', [new ControladorTickets(), 'reasignarTecnicoDelTicket']); // Reasigna técnico (200, 404, 409 o 422)
};
