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

    // FASE 4
    /**
     * Bloquea la fila del técnico hasta que termine la transacción actual y la devuelve.
     * Así, si dos personas le asignan un ticket al mismo tiempo, una espera a la otra
     * y el tope de 5 nunca se rompe. Debe llamarse DENTRO de una transacción.
     */
    public function bloquearTecnicoPorId(int $idDelTecnico): ?array
    {
        $sentencia = BaseDeDatos::obtenerConexion()->prepare( // Prepara la consulta: el id se envía aparte
            'SELECT id, nombre, activo FROM tecnicos WHERE id = :id FOR UPDATE' //? FOR UPDATE bloquea la fila para los demás
        );
        $sentencia->execute(['id' => $idDelTecnico]); 
        $fila = $sentencia->fetch(); 
        return $fila === false ? null : $fila; 
    }

    /**
     * Cuenta los tickets ABIERTOS o EN CURSO de un técnico (su carga actual).
     */
    public function contarTicketsActivosDelTecnico(int $idDelTecnico): int
    {
        $sentencia = BaseDeDatos::obtenerConexion()->prepare( 
            "SELECT COUNT(*) FROM tickets WHERE tecnico_id = :id AND estado IN ('ABIERTO', 'EN CURSO')" // Los cerrados no cuentan
        );
        $sentencia->execute(['id' => $idDelTecnico]); 
        return (int) $sentencia->fetchColumn(); 
    }
}
