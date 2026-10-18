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
}
