<?php
declare(strict_types=1);

namespace Aplicacion\Seguridad;

use Aplicacion\Nucleo\Configuracion; 
use Aplicacion\Nucleo\ExcepcionApi; 
use Aplicacion\Nucleo\Peticion; 

final class VerificadorClaveApi 
{
    public static function verificarClaveApi(Peticion $peticion): void
    {
        $claveCorrecta = (string) Configuracion::obtenerObligatorio('clave_api'); 
        $claveRecibida = $peticion->obtenerEncabezado('X-API-Key'); 
        if ($claveRecibida === null || !hash_equals($claveCorrecta, $claveRecibida)) { 
            throw new ExcepcionApi('NO_AUTORIZADO', 'API key faltante o inválida', 401); 
        }
    }
}
