<?php
declare(strict_types=1); 
namespace Aplicacion\Repositorios; 

use Aplicacion\Nucleo\BaseDeDatos; 
use PDO; // Para el tipo PARAM_INT

final class RepositorioTickets 
{
    private const CONSULTA_BASE_DE_TICKETS =
        'SELECT k.id, k.numero, u.codigo AS codigo_usuario, u.nombre AS usuario, u.cargo, u.area, ' . // Datos del ticket y de quien lo pidió
        'c.nombre AS categoria, p.nombre AS prioridad, t.nombre AS tecnico, k.estado, ' . // Nombres legibles de las tablas de apoyo
        'k.descripcion, k.solucion, k.fecha_creacion, k.fecha_limite, k.fecha_cierre ' . // Descripción, solución y fechas
        'FROM tickets k ' . // Tabla principal
        'JOIN usuarios u ON u.id = k.usuario_id ' . // Une con el solicitante
        'JOIN categorias c ON c.id = k.categoria_id ' . // Une con la categoría
        'JOIN prioridades p ON p.id = k.prioridad_id ' . // Une con la prioridad
        'JOIN tecnicos t ON t.id = k.tecnico_id '; // Une con el técnico asignado

    /**
     * Busca los tickets que cumplen los filtros, una página a la vez.
     * Devuelve ['filas' => lista de tickets de la página, 'total' => cuántos hay en todas las páginas].
     * Filtros posibles: estado, prioridad (nombre), tecnico (id) y texto (número o nombre del usuario).
     */
    public function buscarTicketsPaginados(array $filtros, int $pagina, int $registrosPorPagina): array
    {
        $conexion = BaseDeDatos::obtenerConexion(); 
        [$condicionesSql, $valoresDeLasCondiciones] = $this->construirCondiciones($filtros); // Convierte los filtros en SQL con comodines y sus valores

        $sentenciaDelTotal = $conexion->prepare( 
            'SELECT COUNT(*) ' . 
            'FROM tickets k ' . 
            'JOIN usuarios u ON u.id = k.usuario_id ' . 
            'JOIN prioridades p ON p.id = k.prioridad_id ' . 
            $condicionesSql 
        );
        $sentenciaDelTotal->execute($valoresDeLasCondiciones); 
        $totalDeTickets = (int) $sentenciaDelTotal->fetchColumn(); 

        $sentenciaDeLaPagina = $conexion->prepare( 
            self::CONSULTA_BASE_DE_TICKETS . 
            $condicionesSql . ' ' . 
            'ORDER BY k.fecha_creacion DESC, k.id DESC ' . 
            'LIMIT :limite OFFSET :desplazamiento'
        );

        foreach ($valoresDeLasCondiciones as $nombreDelComodin => $valorDelComodin) { // Entrega cada filtro a la sentencia
            $sentenciaDeLaPagina->bindValue($nombreDelComodin, $valorDelComodin); // Asocia el comodín con su valor
        }
        $sentenciaDeLaPagina->bindValue(':limite', $registrosPorPagina, PDO::PARAM_INT); // El límite debe viajar como número entero
        $sentenciaDeLaPagina->bindValue(':desplazamiento', ($pagina - 1) * $registrosPorPagina, PDO::PARAM_INT); // Página 1 salta 0, página 2 salta 10...
        $sentenciaDeLaPagina->execute(); // Ejecuta la consulta de la página
        return ['filas' => $sentenciaDeLaPagina->fetchAll(), 'total' => $totalDeTickets]; // Entrega la página y el total
    }

    /**
     * Busca un ticket por su número (ej. TK-2026-000018). Devuelve null si no existe.
     */
    public function buscarTicketPorNumero(string $numeroDelTicket): ?array
    {
        $conexion = BaseDeDatos::obtenerConexion(); 
        $sentencia = $conexion->prepare( 
            self::CONSULTA_BASE_DE_TICKETS . 
            'WHERE k.numero = :numero' 
        );
        $sentencia->execute(['numero' => $numeroDelTicket]); 
        $filaDelTicket = $sentencia->fetch(); 
        return $filaDelTicket === false ? null : $filaDelTicket;
    }
    /**
     * Cuenta los tickets de cada estado. Devuelve, por ejemplo, ['ABIERTO' => 6, 'EN CURSO' => 5].
     */
    public function contarTicketsPorEstado(): array
    {
        $conexion = BaseDeDatos::obtenerConexion(); 
        $sentencia = $conexion->query( 
            'SELECT estado, COUNT(*) AS cantidad FROM tickets GROUP BY estado' 
        );
        $cantidadesPorEstado = [];

        foreach ($sentencia->fetchAll() as $fila) { 
            $cantidadesPorEstado[$fila['estado']] = (int) $fila['cantidad']; 
        }
        return $cantidadesPorEstado; 
    }
    /**
     * Cuenta los tickets NO cerrados cuya fecha límite ya pasó.
     */
    public function contarTicketsVencidos(string $momentoActual): int
    {
        $conexion = BaseDeDatos::obtenerConexion(); 
        $sentencia = $conexion->prepare( 
            "SELECT COUNT(*) FROM tickets WHERE estado <> 'CERRADO' AND fecha_limite < :ahora" 
        );
        $sentencia->execute(['ahora' => $momentoActual]); 
        return (int) $sentencia->fetchColumn(); 
    }
    /**
     * Convierte los filtros en un trozo de SQL (WHERE ...) y los valores de sus comodines.
     * Solo agrega una condición si el filtro vino con valor.
     */
    private function construirCondiciones(array $filtros): array
    {
        $condiciones = []; 
        $valores = []; 

        if (!empty($filtros['estado'])) { 
            $condiciones[] = 'k.estado = :estado'; 
            $valores[':estado'] = $filtros['estado']; 
        }

        if (!empty($filtros['prioridad'])) { 
            $condiciones[] = 'p.nombre = :prioridad'; 
            $valores[':prioridad'] = $filtros['prioridad']; 
        }

        if (!empty($filtros['tecnico'])) {
            $condiciones[] = 'k.tecnico_id = :tecnico'; 
            $valores[':tecnico'] = (int) $filtros['tecnico']; 
        }

        if (!empty($filtros['texto'])) { 
            $condiciones[] = '(k.numero LIKE :texto_numero OR u.nombre LIKE :texto_nombre)'; 
            $textoSeguro = addcslashes($filtros['texto'], '\\%_'); // Escapar caracteres, para prevenir que rompan o alteren la consulta
            $valores[':texto_numero'] = '%' . $textoSeguro . '%'; 
            $valores[':texto_nombre'] = '%' . $textoSeguro . '%'; 
        }
        $condicionesSql = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones); 
        return [$condicionesSql, $valores]; 
    }

    // FASE 4 
    /**
     * Inserta un ticket nuevo y devuelve su id. Debe llamarse dentro de la transacción de creación.
     * $datos trae: numero, usuario_id, categoria_id, prioridad_id, tecnico_id, descripcion, fecha_creacion, fecha_limite.
     */
    public function insertarTicket(array $datos): int
    {
        $conexion = BaseDeDatos::obtenerConexion(); 
        $sentencia = $conexion->prepare( // Prepara el INSERT: los valores se envían aparte
            'INSERT INTO tickets (numero, usuario_id, categoria_id, prioridad_id, tecnico_id, estado, descripcion, fecha_creacion, fecha_limite) ' . // Columnas a llenar
            "VALUES (:numero, :usuario_id, :categoria_id, :prioridad_id, :tecnico_id, 'ABIERTO', :descripcion, :fecha_creacion, :fecha_limite)" // Todo ticket nace ABIERTO
        );
        $sentencia->execute([ // Ejecuta enviando los valores como datos
            'numero'         => $datos['numero'], // TK-2026-000026
            'usuario_id'     => $datos['usuario_id'], // Solicitante
            'categoria_id'   => $datos['categoria_id'], // Categoría
            'prioridad_id'   => $datos['prioridad_id'], // Prioridad
            'tecnico_id'     => $datos['tecnico_id'], // Técnico asignado
            'descripcion'    => $datos['descripcion'], // Descripción del problema
            'fecha_creacion' => $datos['fecha_creacion'], // Momento de creación
            'fecha_limite'   => $datos['fecha_limite'], // Fecha límite del SLA
        ]);
        return (int) $conexion->lastInsertId(); // Devuelve el id que MySQL le puso al ticket
    }

    // FASE 5 
    /**
     * Bloquea la fila del ticket hasta que termine la transacción actual y la devuelve.
     * Así, si dos personas cambian el mismo ticket al mismo tiempo, una espera a la otra.
     * Debe llamarse DENTRO de una transacción. Devuelve null si el número no existe.
     */
    public function bloquearTicketPorNumero(string $numeroDelTicket): ?array
    {
        $sentencia = BaseDeDatos::obtenerConexion()->prepare( 
            'SELECT id, numero, estado, tecnico_id FROM tickets WHERE numero = :numero FOR UPDATE' // FOR UPDATE bloquea la fila para los demás
        );
        $sentencia->execute(['numero' => $numeroDelTicket]); 
        $fila = $sentencia->fetch(); 
        return $fila === false ? null : $fila; 
    }
    /**
     * Cambia el estado de un ticket. Al cerrar guarda la solución y la fecha de cierre; en otro caso van en null.
     */
    public function actualizarEstadoDelTicket(int $idDelTicket, string $estadoNuevo, ?string $solucion, ?string $fechaDeCierre): void
    {
        $sentencia = BaseDeDatos::obtenerConexion()->prepare( // Prepara el UPDATE: los valores se envían aparte
            'UPDATE tickets SET estado = :estado, solucion = :solucion, fecha_cierre = :fecha_cierre WHERE id = :id' // El WHERE usa la llave primaria
        );
        $sentencia->execute([ // Ejecuta enviando los valores como datos
            'estado'       => $estadoNuevo, 
            'solucion'     => $solucion, 
            'fecha_cierre' => $fechaDeCierre, 
            'id'           => $idDelTicket, 
        ]);
    }
    /**
     * Cambia el técnico asignado a un ticket.
     */
    public function actualizarTecnicoDelTicket(int $idDelTicket, int $idDelTecnicoNuevo): void
    {
        $sentencia = BaseDeDatos::obtenerConexion()->prepare( 
            'UPDATE tickets SET tecnico_id = :tecnico_id WHERE id = :id' // El WHERE usa la llave primaria
        );
        $sentencia->execute(['tecnico_id' => $idDelTecnicoNuevo, 'id' => $idDelTicket]); // Ejecuta enviando los valores como datos
    }
}
