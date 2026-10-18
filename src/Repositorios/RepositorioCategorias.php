<?php
declare(strict_types=1); 
namespace Aplicacion\Repositorios; 
use Aplicacion\Nucleo\BaseDeDatos; // Para obtener la conexión a MySQL

final class RepositorioCategorias 
{
    /**
     * Devuelve todas las categorías ordenadas por nombre.
     */
    public function listarCategorias(): array
    {
        $conexion = BaseDeDatos::obtenerConexion(); // Obtiene la conexión compartida a MySQL
        $sentencia = $conexion->query( 
            'SELECT id, nombre FROM categorias ORDER BY nombre' 
        );
        return $sentencia->fetchAll(); 
    }

    /**
     * Devuelve las prioridades con sus horas de SLA, de la más urgente a la menos urgente.
     */
    public function listarPrioridades(): array
    {
        $conexion = BaseDeDatos::obtenerConexion();
        $sentencia = $conexion->query(
            'SELECT id, nombre, horas_sla FROM prioridades ORDER BY horas_sla' 
        );
        return $sentencia->fetchAll();
    }

    // FASE 4
    /**
     * Busca una categoría por su id. Devuelve null si no existe.
     */
    public function buscarCategoriaPorId(int $idDeLaCategoria): ?array
    {
        $sentencia = BaseDeDatos::obtenerConexion()->prepare( 
            'SELECT id, nombre FROM categorias WHERE id = :id' 
        );
        $sentencia->execute(['id' => $idDeLaCategoria]); 
        $fila = $sentencia->fetch();
        return $fila === false ? null : $fila; // Convierte "no hay fila" en null
    }

    /**
     * Busca una prioridad por su nombre (Alta, Media o Baja). Devuelve null si no existe.
     */
    public function buscarPrioridadPorNombre(string $nombreDeLaPrioridad): ?array
    {
        $sentencia = BaseDeDatos::obtenerConexion()->prepare( 
            'SELECT id, nombre, horas_sla FROM prioridades WHERE nombre = :nombre' 
        );
        $sentencia->execute(['nombre' => $nombreDeLaPrioridad]); 
        $fila = $sentencia->fetch(); 
        return $fila === false ? null : $fila; // Convierte "no hay fila" en null
    }
}
