<?php
declare(strict_types=1); 
namespace Aplicacion\Repositorios; 
use Aplicacion\Nucleo\BaseDeDatos; 

final class RepositorioHistorial 
{
    /**
     * Historial completo de un ticket, del cambio más antiguo al más reciente.
     */
    public function listarHistorialDelTicket(int $idDelTicket): array
    {
        $conexion = BaseDeDatos::obtenerConexion(); 
        $sentencia = $conexion->prepare( 
            'SELECT fecha_creacion, estado_anterior, estado_nuevo, observacion ' . 
            'FROM ticket_historial ' . 
            'WHERE ticket_id = :ticket_id ' . 
            'ORDER BY fecha_creacion ASC, id ASC' 
        );
        $sentencia->execute(['ticket_id' => $idDelTicket]); 
        return $sentencia->fetchAll(); 
    }
}
