<?php
/**
 * ARCHIVO: src/Controladores/ControladorTickets.php
 * CAPA: Controladores (reciben la petición y responden JSON)
 * FASE: 3 (listar y detalle), 4 (crear), 5 (estado y técnico)
 *
 * QUÉ VA AQUÍ (Fase 3: listarTickets y obtenerDetalleDelTicket; los demás llegan después):
 *   Recibe las peticiones de /api/tickets... y responde JSON. Un método por
 *   endpoint:
 *     listarTickets()               GET   /api/tickets                  -> 200
 *     obtenerDetalleDelTicket()     GET   /api/tickets/{numero}         -> 200 / 404
 *     crearTicket()                 POST  /api/tickets                  -> 201 / 422
 *     cambiarEstadoDelTicket()      PATCH /api/tickets/{numero}/estado  -> 200 / 404 / 409 / 422
 *     reasignarTecnicoDelTicket()   PATCH /api/tickets/{numero}/tecnico -> 200 / 404 / 409
 *
 * CÓMO TRABAJA:
 *   Lee los datos de la Peticion, se los pasa al ServicioTickets y convierte
 *   el resultado en una respuesta. Es "delgado": casi no piensa.
 *
 * QUÉ NO VA AQUÍ:
 *   Reglas de negocio (eso es de ServicioTickets) ni SQL (eso es de los
 *   Repositorios).
 */
declare(strict_types=1); // Activa la verificación estricta de tipos

namespace Aplicacion\Controladores; // Ubicación lógica de esta clase (carpeta src/Controladores)

use Aplicacion\Nucleo\Peticion; // Tipo de la petición recibida
use Aplicacion\Nucleo\Respuesta; // Para enviar el JSON
use Aplicacion\Servicios\ServicioTickets; // Lógica de negocio
use Aplicacion\Validadores\ValidadorTickets; // Validación de datos de entrada

final class ControladorTickets // "final": ninguna otra clase puede heredar de esta
{
    private ServicioTickets $servicioDeTickets; // Servicio con las reglas de tickets
    private ValidadorTickets $validadorDeTickets; // Revisa los datos que llegan

    public function __construct() // Se ejecuta al crear el controlador
    {
        $this->servicioDeTickets = new ServicioTickets(); // Crea el servicio que usará
        $this->validadorDeTickets = new ValidadorTickets(); // Crea el validador que usará
    }
    /**
     * GET /api/tickets  ->  200 con la página de tickets y "meta".
     */
    public function listarTickets(Peticion $peticion, array $parametrosDeLaRuta): void
    {
        $parametros = $this->validadorDeTickets->validarFiltrosDelListado($peticion); // Revisa filtros y página (lanza 422 si están mal)
        $resultado = $this->servicioDeTickets->listarTickets( // Pide la página de tickets
            $parametros['filtros'], // Filtros ya limpios
            $parametros['pagina'], // Página pedida
            $parametros['registrosPorPagina'] // Tamaño de página
        );
        Respuesta::enviarJson($resultado); // Responde 200 con {data, meta}
    }
    /**
     * GET /api/tickets/{numero}  ->  200 con el detalle y su historial, o 404.
     */
    public function obtenerDetalleDelTicket(Peticion $peticion, array $parametrosDeLaRuta): void
    {
        Respuesta::enviarJson($this->servicioDeTickets->obtenerDetalleDelTicket($parametrosDeLaRuta['numero'])); // Responde 200 (o 404 si no existe)
    }
}
