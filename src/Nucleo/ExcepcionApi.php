<?php
declare(strict_types=1); 
namespace Aplicacion\Nucleo; 
final class ExcepcionApi extends \RuntimeException // Esta clase hereda con \RuntimeException todas las funciones de los errores en tiempo de ejecucion la barra \ indica que la busque en la raiz global de PHP
{
    public function __construct( 
        private string $codigoDeError, 
        string $mensajeParaElCliente, 
        private int $estadoHttp, 
        private array $camposConError = [] 
    ) {
        parent::__construct($mensajeParaElCliente); 
    }

    public function obtenerCodigoDeError(): string
    {
        return $this->codigoDeError;
    }

    public function obtenerEstadoHttp(): int
    {
        return $this->estadoHttp; 
    }

    public function obtenerCamposConError(): array
    {
        return $this->camposConError; 
    }
}
