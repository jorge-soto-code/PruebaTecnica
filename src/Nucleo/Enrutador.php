<?php
declare(strict_types=1);
namespace Aplicacion\Nucleo;

final class Enrutador 
{
    /** @var array<int, array{metodoHttp:string, expresionRegular:string, manejador:callable}> */
    private array $rutasRegistradas = []; 
    public function registrarRutaGet(string $patronDeLaRuta, callable $manejador): void
    {
        $this->registrarRuta('GET', $patronDeLaRuta, $manejador);
    }

    public function registrarRutaPost(string $patronDeLaRuta, callable $manejador): void
    {
        $this->registrarRuta('POST', $patronDeLaRuta, $manejador); 
    }

    public function registrarRutaPatch(string $patronDeLaRuta, callable $manejador): void
    {
        $this->registrarRuta('PATCH', $patronDeLaRuta, $manejador); 
    }

    private function registrarRuta(string $metodoHttp, string $patronDeLaRuta, callable $manejador): void
    {
        $expresionRegular = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $patronDeLaRuta) . '$#';
        $this->rutasRegistradas[] = [ 
            'metodoHttp'       => $metodoHttp, 
            'expresionRegular' => $expresionRegular, 
            'manejador'        => $manejador, 
        ];
    }
    /**
     * Busca la ruta que corresponde a la petición y ejecuta su manejador.
     */
    public function atenderPeticion(Peticion $peticion): void
    {
        $metodosPermitidosParaLaRuta = [];
        foreach ($this->rutasRegistradas as $rutaRegistrada) { 
            $coincidenciasEnLaDireccion = []; 
            $laDireccionCoincide = preg_match( 
                $rutaRegistrada['expresionRegular'], 
                $peticion->obtenerRuta(), 
                $coincidenciasEnLaDireccion 
            );
            if (!$laDireccionCoincide) { 
                continue; 
            }
            if ($rutaRegistrada['metodoHttp'] !== $peticion->obtenerMetodoHttp()) { 
                $metodosPermitidosParaLaRuta[] = $rutaRegistrada['metodoHttp']; 
                continue; 
            }
            $parametrosDeLaRuta = []; 
            foreach ($coincidenciasEnLaDireccion as $nombreDelParametro => $valorDelParametro) { 
                if (is_string($nombreDelParametro)) { 
                    $parametrosDeLaRuta[$nombreDelParametro] = rawurldecode($valorDelParametro);
                }
            }
            $rutaRegistrada['manejador']($peticion, $parametrosDeLaRuta);
            return; 
        }
        if ($metodosPermitidosParaLaRuta !== []) { 
            header('Allow: ' . implode(', ', array_unique($metodosPermitidosParaLaRuta))); 
            throw new ExcepcionApi('METODO_NO_PERMITIDO', 'Método no permitido para esta ruta', 405); 
        }
        throw new ExcepcionApi('NO_ENCONTRADO', 'Ruta no encontrada', 404); 
    }
}
