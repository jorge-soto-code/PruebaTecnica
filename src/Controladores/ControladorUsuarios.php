<?php
declare(strict_types=1); 
namespace Aplicacion\Controladores; 

use Aplicacion\Nucleo\Peticion; 
use Aplicacion\Nucleo\Respuesta; 
use Aplicacion\Servicios\ServicioTickets; 

final class ControladorUsuarios 
{
    private ServicioTickets $servicioDeTickets; 
    public function __construct() 
    {
        $this->servicioDeTickets = new ServicioTickets(); 
    }
    /**
     * GET /api/usuarios/{codigo}  ->  200 con los datos, o 404 si no existe.
     */
    public function mostrarUsuarioPorCodigo(Peticion $peticion, array $parametrosDeLaRuta): void
    {
        $usuario = $this->servicioDeTickets->obtenerUsuarioPorCodigo($parametrosDeLaRuta['codigo']); 
        Respuesta::enviarJson($usuario); 
    }
}
