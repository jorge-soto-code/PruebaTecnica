<?php
declare(strict_types=1); 

use Aplicacion\Nucleo\Configuracion; 
use Aplicacion\Nucleo\ExcepcionApi; 
use Aplicacion\Nucleo\Respuesta; 

// ---- 1) Carga automática de clases: Aplicacion\Nucleo\Enrutador -> src/Nucleo/Enrutador.php ----
spl_autoload_register(static function (string $nombreCompletoDeLaClase): void { 
    $prefijoDelProyecto = 'Aplicacion\\'; // Todas las clases del proyecto empiezan con este nombre

    if (!str_starts_with($nombreCompletoDeLaClase, $prefijoDelProyecto)) { 
        return; 
    }

    $nombreSinPrefijo = substr($nombreCompletoDeLaClase, strlen($prefijoDelProyecto)); 
    $rutaDelArchivoDeLaClase = __DIR__ . '/' . str_replace('\\', '/', $nombreSinPrefijo) . '.php'; 

    if (is_file($rutaDelArchivoDeLaClase)) { 
        require $rutaDelArchivoDeLaClase;
    }
});

// ---- 2) Nunca mostrar errores al cliente; guardarlos en el log del servidor ----
ini_set('display_errors', '0'); 
ini_set('log_errors', '1'); 
error_reporting(E_ALL); 

// ---- 3) Una advertencia de PHP se trata como un error (y termina en el manejador de abajo) ----
set_error_handler(static function (int $nivelDelError, string $mensajeDelError, string $archivoDelError, int $lineaDelError): bool {
    $esUnAvisoDeCodigoObsoleto = in_array($nivelDelError, [E_DEPRECATED, E_USER_DEPRECATED], true); 

    if ($esUnAvisoDeCodigoObsoleto || !(error_reporting() & $nivelDelError)) { 
        return false; 
    }

    throw new ErrorException($mensajeDelError, 0, $nivelDelError, $archivoDelError, $lineaDelError); 
});

// ---- 4) Manejador global: TODO error que no se atrape antes termina aquí ----
set_exception_handler(static function (Throwable $errorAtrapado): void { 
    if ($errorAtrapado instanceof ExcepcionApi) { 
        Respuesta::enviarError( 
            $errorAtrapado->obtenerCodigoDeError(), 
            $errorAtrapado->getMessage(), 
            $errorAtrapado->obtenerCamposConError(), 
            $errorAtrapado->obtenerEstadoHttp() 
        );
        return; 
    }

    // Error inesperado: el detalle va al log del servidor, el cliente solo ve un mensaje genérico
    $detalleParaElLog = sprintf( 
        '[API] %s: %s en %s:%d', 
        get_class($errorAtrapado), 
        $errorAtrapado->getMessage(), 
        $errorAtrapado->getFile(), 
        $errorAtrapado->getLine() 
    );

    if (Configuracion::obtener('depuracion', false)) { 
        $detalleParaElLog .= "\n" . $errorAtrapado->getTraceAsString(); 
    }

    error_log($detalleParaElLog);

    Respuesta::enviarError( 
        'ERROR_INTERNO', 
        'Ocurrió un error interno. Intenta de nuevo más tarde.', 
        [], 
        500 
    );
});

// ---- 5) Zona horaria ----
date_default_timezone_set((string) Configuracion::obtener('zona_horaria', 'America/Bogota')); 

