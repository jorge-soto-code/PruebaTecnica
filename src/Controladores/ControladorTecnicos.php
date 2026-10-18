<?php
declare(strict_types=1); 
namespace Aplicacion\Controladores; 

use Aplicacion\Nucleo\Peticion; 
use Aplicacion\Nucleo\Respuesta; 
use Aplicacion\Servicios\ServicioTecnicos; 

final class ControladorTecnicos 
{
    private ServicioTecnicos $servicioDeTecnicos; 
    public function __construct() 
    {
        $this->servicioDeTecnicos = new ServicioTecnicos(); 
    }
    /**
     * GET /api/tecnicos  ->  200 con los técnicos activos y su carga.
     */
    public function listarTecnicosActivosConCarga(Peticion $peticion, array $parametrosDeLaRuta): void
    {
        Respuesta::enviarJson(['data' => $this->servicioDeTecnicos->listarTecnicosActivosConCarga()]); 
    }
}
