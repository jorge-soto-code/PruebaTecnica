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

    // FASE 4 
     /**
     * Agrega una línea al historial del ticket. Solo inserta: el historial nunca se edita ni se borra.
     */
    public function registrarCambioEnElHistorial(
        int $idDelTicket,
        ?string $estadoAnterior, 
        string $estadoNuevo, 
        string $observacion, 
        string $fechaDelCambio 
    ): void {
        $sentencia = BaseDeDatos::obtenerConexion()->prepare( // Prepara el INSERT: los valores se envían aparte
            'INSERT INTO ticket_historial (ticket_id, estado_anterior, estado_nuevo, observacion, fecha_creacion) ' . // Columnas a llenar
            'VALUES (:ticket_id, :estado_anterior, :estado_nuevo, :observacion, :fecha)' 
        );
        $sentencia->execute([ // Ejecuta enviando los valores como datos
            'ticket_id'       => $idDelTicket, 
            'estado_anterior' => $estadoAnterior, 
            'estado_nuevo'    => $estadoNuevo, 
            'observacion'     => $observacion, 
            'fecha'           => $fechaDelCambio, 
        ]);
    }
}
