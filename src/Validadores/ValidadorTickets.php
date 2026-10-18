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

    // FASE 4 

    /**
     * Revisa el JSON de POST /tickets. Si algo está mal lanza 422 con la lista de campos.
     * Devuelve los datos ya limpios: codigo_usuario, categoria_id, descripcion, prioridad, tecnico_id.
     */
    public function validarDatosParaCrearTicket(array $cuerpo): array
    {
        $camposConError = []; // Aquí se anotan los problemas encontrados
        $codigoDelUsuario = isset($cuerpo['codigo_usuario']) && is_string($cuerpo['codigo_usuario']) ? trim($cuerpo['codigo_usuario']) : ''; // Texto sin espacios sobrantes
        if ($codigoDelUsuario === '') { // Si falta o está vacío
            $camposConError['codigo_usuario'] = 'Es obligatorio'; // Anota el error
        }

        $idDeLaCategoria = $this->leerIdObligatorio($cuerpo, 'categoria_id'); // Debe ser un número entero positivo
        if ($idDeLaCategoria === null) { // Si no es válido
            $camposConError['categoria_id'] = 'Es obligatoria y debe ser el número (id) de una categoría'; // Anota el error
        }

        $descripcion = isset($cuerpo['descripcion']) && is_string($cuerpo['descripcion']) ? trim($cuerpo['descripcion']) : ''; // Texto sin espacios sobrantes
        $largoDeLaDescripcion = mb_strlen($descripcion); // Cuenta letras (no bytes: las tildes cuentan como una)
        if ($largoDeLaDescripcion < 20 || $largoDeLaDescripcion > 1000) { // Fuera del rango permitido
            $camposConError['descripcion'] = 'Debe tener entre 20 y 1000 caracteres (tiene ' . $largoDeLaDescripcion . ')'; // Anota el error
        }

        $prioridad = isset($cuerpo['prioridad']) && is_string($cuerpo['prioridad']) ? ucfirst(mb_strtolower(trim($cuerpo['prioridad']))) : ''; // "alta" se acepta como "Alta"
        if (!in_array($prioridad, self::PRIORIDADES_VALIDAS, true)) { // Si no es una prioridad conocida
            $camposConError['prioridad'] = 'Debe ser Alta, Media o Baja'; // Anota el error
        }

        $idDelTecnico = $this->leerIdObligatorio($cuerpo, 'tecnico_id'); // Debe ser un número entero positivo
        if ($idDelTecnico === null) { // Si no es válido
            $camposConError['tecnico_id'] = 'Es obligatorio y debe ser el número (id) de un técnico'; // Anota el error
        }

        if ($camposConError !== []) { // Si se encontró algún problema
            throw new ExcepcionApi('VALIDACION_FALLIDA', 'Los datos del ticket no son válidos', 422, $camposConError); // Responde 422 con el detalle
        }

        return [ // Entrega los datos ya limpios
            'codigo_usuario' => $codigoDelUsuario, // Código del solicitante
            'categoria_id'   => $idDeLaCategoria, // Categoría
            'descripcion'    => $descripcion, // Descripción
            'prioridad'      => $prioridad, // Prioridad
            'tecnico_id'     => $idDelTecnico, // Técnico
        ];
    }
    /**
     * Lee un campo del JSON que debe ser un entero positivo (acepta 3 o "3"). Devuelve null si no lo es.
     */
    private function leerIdObligatorio(array $cuerpo, string $nombreDelCampo): ?int
    {
        $valor = $cuerpo[$nombreDelCampo] ?? null; // Lee el campo (null si no vino)
        if (is_int($valor) && $valor >= 1) { // Llegó como número entero válido
            return $valor; // Lo devuelve tal cual
        }
        if (is_string($valor) && ctype_digit($valor) && (int) $valor >= 1) { // Llegó como texto con solo dígitos
            return (int) $valor; // Lo convierte a número
        }
        return null; // No es válido
    }

    // FASE 5 
    /**
     * Revisa el JSON de PATCH /tickets/{numero}/estado. Si algo está mal lanza 422.
     * Devuelve: estado, solucion (null si no viene) y observacion (null si no viene).
     * Ojo: aquí solo se revisa que los datos tengan forma correcta; si el salto de estado
     * es válido lo decide ServicioFlujoDeEstados (y responde 409).
     */
    public function validarDatosParaCambiarEstado(array $cuerpo): array
    {
        $camposConError = []; 
        $estado = isset($cuerpo['estado']) && is_string($cuerpo['estado']) ? mb_strtoupper(trim($cuerpo['estado'])) : ''; // "en curso" se acepta como "EN CURSO"
        if (!in_array($estado, self::ESTADOS_VALIDOS, true)) { // Si no es uno de los tres estados
            $camposConError['estado'] = 'Debe ser ABIERTO, EN CURSO o CERRADO'; 
        }

        $solucion = $this->leerTextoOpcional($cuerpo, 'solucion', 2000, $camposConError); // Solución aplicada (opcional salvo al cerrar)
        if ($estado === 'CERRADO' && $solucion === null && !isset($camposConError['solucion'])) { // Para cerrar es obligatorio registrar la solución
            $camposConError['solucion'] = 'Es obligatoria para cerrar el ticket'; // Anota el error
        }

        $observacion = $this->leerTextoOpcional($cuerpo, 'observacion', 1000, $camposConError); // Nota del cambio (opcional)
        if ($camposConError !== []) {
            throw new ExcepcionApi('VALIDACION_FALLIDA', 'Los datos para cambiar el estado no son válidos', 422, $camposConError);
        }
        return ['estado' => $estado, 'solucion' => $solucion, 'observacion' => $observacion]; 
    }
    /**
     * Revisa el JSON de PATCH /tickets/{numero}/tecnico. Si algo está mal lanza 422.
     * Devuelve: tecnico_id y observacion (null si no viene).
     */
    public function validarDatosParaReasignarTecnico(array $cuerpo): array
    {
        $camposConError = []; 
        $idDelTecnico = $this->leerIdObligatorio($cuerpo, 'tecnico_id'); 
        if ($idDelTecnico === null) { // Si no es válido
            $camposConError['tecnico_id'] = 'Es obligatorio y debe ser el número (id) de un técnico'; 
        }

        $observacion = $this->leerTextoOpcional($cuerpo, 'observacion', 1000, $camposConError); 
        if ($camposConError !== []) { 
            throw new ExcepcionApi('VALIDACION_FALLIDA', 'Los datos para reasignar el técnico no son válidos', 422, $camposConError); 
        }
        return ['tecnico_id' => $idDelTecnico, 'observacion' => $observacion]; // Entrega los datos ya limpios
    }

    /**
     * Lee un texto opcional del JSON. Devuelve null si no vino o está vacío; anota error si no es texto o es muy largo.
     */
    private function leerTextoOpcional(array $cuerpo, string $nombreDelCampo, int $largoMaximo, array &$camposConError): ?string
    {
        if (!isset($cuerpo[$nombreDelCampo]) || $cuerpo[$nombreDelCampo] === '') { 
            return null; // Es opcional: no hay nada que revisar
        }

        if (!is_string($cuerpo[$nombreDelCampo])) { 
            $camposConError[$nombreDelCampo] = 'Debe ser un texto'; 
            return null; // No sigue revisando este campo
        }

        $texto = trim($cuerpo[$nombreDelCampo]); 
        if (mb_strlen($texto) > $largoMaximo) { 
            $camposConError[$nombreDelCampo] = 'No puede superar ' . $largoMaximo . ' caracteres'; 
            return null; 
        }
        return $texto === '' ? null : $texto; 
    }
}
