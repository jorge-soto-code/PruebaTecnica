<?php
declare(strict_types=1); 
namespace Aplicacion\Nucleo; 
final class Configuracion 
{
    private const VARIABLES_DE_ENTORNO = [
        'base_datos.servidor' => 'BD_SERVIDOR', 
        'base_datos.puerto'   => 'BD_PUERTO',   
        'base_datos.nombre'   => 'BD_NOMBRE',   
        'base_datos.usuario'  => 'BD_USUARIO',  
        'base_datos.clave'    => 'BD_CLAVE',    
        'clave_api'           => 'CLAVE_API',  
    ];

    private static ?array $valoresDelArchivo = null;
    public static function obtener(string $claveDeConfiguracion, mixed $valorPorDefecto = null): mixed
    {
        $nombreDeLaVariableDeEntorno = self::VARIABLES_DE_ENTORNO[$claveDeConfiguracion] ?? null;
        if ($nombreDeLaVariableDeEntorno !== null) { 
            $valorDeLaVariableDeEntorno = getenv($nombreDeLaVariableDeEntorno); 

            if ($valorDeLaVariableDeEntorno !== false) { 
                return $valorDeLaVariableDeEntorno; 
            }
        }

        $valorEncontrado = self::leerValoresDelArchivo(); 
        foreach (explode('.', $claveDeConfiguracion) as $parteDeLaClave) { 
            if (!is_array($valorEncontrado) || !array_key_exists($parteDeLaClave, $valorEncontrado)) { 
                return $valorPorDefecto; 
            }
            $valorEncontrado = $valorEncontrado[$parteDeLaClave]; 
        }
        return $valorEncontrado; 
    }

    public static function obtenerObligatorio(string $claveDeConfiguracion): mixed
    {
        $valorObtenido = self::obtener($claveDeConfiguracion); 
        if ($valorObtenido === null) { 
            throw new \RuntimeException( 
                "Falta la configuración '$claveDeConfiguracion'. Copia config.example.php como config.php y complétalo." 
            );
        }
        return $valorObtenido; 
    }

    private static function leerValoresDelArchivo(): array
    {
        if (self::$valoresDelArchivo === null) { 
            $rutaDelArchivoDeConfiguracion = dirname(__DIR__, 2) . '/config.php'; 

            self::$valoresDelArchivo = is_file($rutaDelArchivoDeConfiguracion) 
                ? (require $rutaDelArchivoDeConfiguracion)
                : []; 
        }
        return self::$valoresDelArchivo; 
    }
}
