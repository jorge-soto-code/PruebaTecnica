<?php
declare(strict_types=1); 
namespace Aplicacion\Nucleo; 

final class Peticion 
{
    /**
     * @param array<string,string> $encabezadosEnMinuscula nombres en minúscula, por ejemplo 'x-api-key'
     */
    public function __construct( 
        private string $metodoHttp, 
        private string $rutaSinPrefijo, 
        private array $parametrosDeConsulta = [], 
        private array $encabezadosEnMinuscula = [],
        private string $cuerpoEnTexto = '' 
    ) {
    }

    public static function crearDesdeLasVariablesDelServidor(): self
    {
        $direccionSolicitada = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $carpetaDeLaApi = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

        if ($carpetaDeLaApi !== '' && str_starts_with($direccionSolicitada, $carpetaDeLaApi)) { 
            $direccionSolicitada = substr($direccionSolicitada, strlen($carpetaDeLaApi)); 
        }
        $rutaSinPrefijo = '/' . trim($direccionSolicitada, '/'); 
        $encabezadosEnMinuscula = []; 
        foreach ($_SERVER as $nombreDeLaVariable => $valorDeLaVariable) { 
            if (str_starts_with($nombreDeLaVariable, 'HTTP_')) { 
                $nombreDelEncabezado = strtolower(str_replace('_', '-', substr($nombreDeLaVariable, 5)));
                $encabezadosEnMinuscula[$nombreDelEncabezado] = (string) $valorDeLaVariable;
            }
        }

        return new self( 
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), 
            $rutaSinPrefijo, 
            $_GET,
            $encabezadosEnMinuscula, 
            (string) file_get_contents('php://input') 
        );
    }

    public function obtenerMetodoHttp(): string
    {
        return $this->metodoHttp; 
    }

    public function obtenerRuta(): string
    {
        return $this->rutaSinPrefijo; 
    }

    public function obtenerParametroDeConsulta(string $nombreDelParametro, mixed $valorPorDefecto = null): mixed
    {
        return $this->parametrosDeConsulta[$nombreDelParametro] ?? $valorPorDefecto; 
    }

    public function obtenerEncabezado(string $nombreDelEncabezado): ?string
    {
        return $this->encabezadosEnMinuscula[strtolower($nombreDelEncabezado)] ?? null; 
    }
    /**
     * Cuerpo de la petición convertido de JSON a arreglo.
     * Si viene vacío devuelve []. Si el JSON está mal formado responde 422.
     */
    public function obtenerCuerpoJson(): array
    {
        $textoDelCuerpo = trim($this->cuerpoEnTexto); 
        if ($textoDelCuerpo === '') { 
            return []; 
        }

        try { 
            $datosDelCuerpo = json_decode($textoDelCuerpo, true, 512, JSON_THROW_ON_ERROR); 
        } catch (\JsonException) {
            throw new ExcepcionApi( 
                'VALIDACION', 
                'El cuerpo debe ser un JSON válido', 
                422, 
                ['cuerpo' => 'JSON mal formado'] 
            );
        }

        if (!is_array($datosDelCuerpo)) { 
            throw new ExcepcionApi( 
                'VALIDACION', 
                'El cuerpo debe ser un objeto JSON', 
                422, 
                ['cuerpo' => 'Se esperaba un objeto'] 
            );
        }
        return $datosDelCuerpo; 
    }
}
