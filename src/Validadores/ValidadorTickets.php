<?php
/**
 * ARCHIVO: src/Validadores/ValidadorTickets.php
 * CAPA: Lógica de negocio (validación de datos de entrada)
 * FASE: 3 (filtros del listado) y 4 (datos para crear)
 *
 * QUÉ VA AQUÍ:
 *   Revisa que los datos que llegan en el JSON sean correctos ANTES de tocar
 *   la base de datos:
 *     validarFiltrosDelListado() (Fase 3): revisa estado, prioridad, tecnico, q, page y per_page.
 *     (Fase 4) validarDatosParaCrearTicket():
 *       - codigo_usuario: obligatorio
 *       - categoria_id: obligatoria y existente
 *       - descripcion: obligatoria, mínimo 20 y máximo 1.000 caracteres
 *       - prioridad: Alta / Media / Baja
 *       - tecnico_id: obligatorio
 *     validarDatosParaCambiarEstado():
 *       - estado: solo "EN CURSO" o "CERRADO"
 *       - solucion: obligatoria al cerrar
 *     validarDatosParaReasignarTecnico():
 *       - tecnico_id: obligatorio
 *   Si algo falla, devuelve la lista de campos con su mensaje y la API
 *   responde 422 con el formato único de error ("fields").
 *
 * RECUERDA:
 *   La validación en el servidor es obligatoria aunque la página ya valide.
 */
declare(strict_types=1);
namespace Aplicacion\Validadores; 

use Aplicacion\Nucleo\Configuracion; // Para leer los tamaños de página de config.php
use Aplicacion\Nucleo\ExcepcionApi; // Para lanzar el error 422
use Aplicacion\Nucleo\Peticion; // Para leer los parámetros de la dirección

final class ValidadorTickets 
{
    private const ESTADOS_VALIDOS = ['ABIERTO', 'EN CURSO', 'CERRADO']; // Los únicos estados que existen
    private const PRIORIDADES_VALIDAS = ['Alta', 'Media', 'Baja']; // Las únicas prioridades que existen

    /**
     * Revisa y limpia los filtros de GET /tickets. Si algo está mal lanza un 422.
     * Devuelve ['filtros' => [...], 'pagina' => n, 'registrosPorPagina' => n].
     */
    public function validarFiltrosDelListado(Peticion $peticion): array
    {
        $camposConError = []; // Aquí se anotan los problemas encontrados
        $filtros = []; // Aquí se guardan los filtros ya limpios

        $estado = trim((string) $peticion->obtenerParametroDeConsulta('estado', '')); // Lee "estado" sin espacios sobrantes
        if ($estado !== '') { // Solo si se envió
            $estado = mb_strtoupper($estado); // Acepta "cerrado" o "CERRADO"
            if (!in_array($estado, self::ESTADOS_VALIDOS, true)) { // Si no es uno de los tres estados
                $camposConError['estado'] = 'Debe ser ABIERTO, EN CURSO o CERRADO'; // Anota el error
            } else { 
                $filtros['estado'] = $estado; 
            }
        }

        $prioridad = trim((string) $peticion->obtenerParametroDeConsulta('prioridad', '')); // Lee "prioridad"
        if ($prioridad !== '') { // Solo si se envió
            $prioridad = ucfirst(mb_strtolower($prioridad)); // Acepta "alta" o "ALTA" y lo deja como "Alta"
            if (!in_array($prioridad, self::PRIORIDADES_VALIDAS, true)) { // Si no es una prioridad conocida
                $camposConError['prioridad'] = 'Debe ser Alta, Media o Baja'; // Anota el error
            } else { 
                $filtros['prioridad'] = $prioridad; // La guarda
            }
        }

        $tecnico = trim((string) $peticion->obtenerParametroDeConsulta('tecnico', '')); // Lee "tecnico" (id del técnico)
        if ($tecnico !== '') { // Solo si se envió
            if (!ctype_digit($tecnico) || (int) $tecnico < 1) { // Debe ser un número entero positivo
                $camposConError['tecnico'] = 'Debe ser el número (id) de un técnico'; // Anota el error
            } else { // Si es válido
                $filtros['tecnico'] = (int) $tecnico; // Lo guarda como número
            }
        }

        $texto = trim((string) $peticion->obtenerParametroDeConsulta('q', '')); // Lee el texto libre "q"
        if ($texto !== '') { // Solo si se escribió algo
            $filtros['texto'] = mb_substr($texto, 0, 100); // Limita a 100 letras para no hacer búsquedas absurdas
        }
        $pagina = $this->leerEnteroPositivo($peticion, 'page', 1, $camposConError); // Página pedida (por defecto 1)
        $porPaginaPorDefecto = (int) Configuracion::obtener('registros_por_pagina_por_defecto', 10); // 10 según la prueba
        $porPaginaMaximo = (int) Configuracion::obtener('registros_por_pagina_maximo', 50); // Tope para que nadie pida miles de filas
        $registrosPorPagina = $this->leerEnteroPositivo($peticion, 'per_page', $porPaginaPorDefecto, $camposConError); // Tamaño de página

        if ($registrosPorPagina > $porPaginaMaximo) { // Si pide más del máximo permitido
            $camposConError['per_page'] = 'No puede ser mayor que ' . $porPaginaMaximo; // Anota el error
        }

        if ($camposConError !== []) { // Si se encontró algún problema
            throw new ExcepcionApi('VALIDACION_FALLIDA', 'Los filtros enviados no son válidos', 422, $camposConError); // Responde 422 con el detalle
        }
        
        return ['filtros' => $filtros, 'pagina' => $pagina, 'registrosPorPagina' => $registrosPorPagina]; // Entrega todo ya limpio
    }

    /**
     * Lee un parámetro que debe ser entero mayor o igual a 1. Si es inválido, lo anota en $camposConError.
     */
    private function leerEnteroPositivo(Peticion $peticion, string $nombre, int $valorPorDefecto, array &$camposConError): int
    {
        $valorEnTexto = trim((string) $peticion->obtenerParametroDeConsulta($nombre, '')); // Lee el parámetro como texto

        if ($valorEnTexto === '') { // Si no se envió
            return $valorPorDefecto; // Usa el valor por defecto
        }

        if (!ctype_digit($valorEnTexto) || (int) $valorEnTexto < 1) { // Debe ser un entero de 1 en adelante
            $camposConError[$nombre] = 'Debe ser un número entero mayor o igual a 1'; // Anota el error
            return $valorPorDefecto; // Devuelve algo válido; el error ya quedó anotado
        }

        return (int) $valorEnTexto; // Devuelve el número ya convertido
    }
}
