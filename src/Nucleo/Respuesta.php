<?php
declare(strict_types=1); 
namespace Aplicacion\Nucleo;
final class Respuesta 
{
    public static function enviarJson(mixed $datosParaEnviar, int $estadoHttp = 200): void
    {
        http_response_code($estadoHttp); 
        header('Content-Type: application/json'); 
        echo json_encode( 
            $datosParaEnviar,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    public static function enviarError(
        string $codigoDeError, 
        string $mensajeParaElCliente, 
        array $camposConError = [], 
        int $estadoHttp = 400 
    ): void {
        self::enviarJson([ 
            'error' => [ 
                'code'    => $codigoDeError, 
                'message' => $mensajeParaElCliente, 
                'fields'  => (object) $camposConError, 
            ],
        ], $estadoHttp);
    }
}
