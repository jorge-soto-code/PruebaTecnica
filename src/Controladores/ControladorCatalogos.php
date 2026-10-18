<?php
declare(strict_types=1); 
namespace Aplicacion\Controladores; 

use Aplicacion\Nucleo\Peticion; 
use Aplicacion\Nucleo\Respuesta; 
use Aplicacion\Servicios\ServicioTickets; 

final class ControladorCatalogos // "final": ninguna otra clase puede heredar de esta
{
    private ServicioTickets $servicioDeTickets; 
    public function __construct() 
    {
        $this->servicioDeTickets = new ServicioTickets(); 
    }
    /**
     * GET /api/categorias  ->  200 con categorías y prioridades.
     */
    public function listarCategoriasYPrioridades(Peticion $peticion, array $parametrosDeLaRuta): void
    {
        Respuesta::enviarJson($this->servicioDeTickets->listarCategoriasYPrioridades()); 
    }
    /**
     * GET /api/resumen  ->  200 con los números del tablero.
     */
    public function obtenerResumenDelTablero(Peticion $peticion, array $parametrosDeLaRuta): void
    {
        Respuesta::enviarJson($this->servicioDeTickets->obtenerResumenDelTablero()); 
    }
}
