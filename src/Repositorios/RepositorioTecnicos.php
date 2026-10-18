<?php
declare(strict_types=1); 
namespace Aplicacion\Repositorios; 

use Aplicacion\Nucleo\BaseDeDatos; 

final class RepositorioTecnicos 
{
    /**
     * Técnicos activos con su carga (tickets ABIERTOS o EN CURSO), de menor a mayor carga.
     */
    public function listarTecnicosActivosConCarga(): array
    {
        $conexion = BaseDeDatos::obtenerConexion();
        $sentencia = $conexion->query( 
            'SELECT t.id, t.nombre, t.correo, COUNT(k.id) AS carga ' . 
            'FROM tecnicos t ' . // Parte de todos los técnicos
            "LEFT JOIN tickets k ON k.tecnico_id = t.id AND k.estado IN ('ABIERTO', 'EN CURSO') " . 
            'WHERE t.activo = 1 ' . 
            'GROUP BY t.id, t.nombre, t.correo ' . 
            'ORDER BY carga ASC, t.nombre ASC' 
        );
        return $sentencia->fetchAll(); 
    }
}
