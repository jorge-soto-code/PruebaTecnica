<?php
declare(strict_types=1); 

namespace Aplicacion\Repositorios; 

use Aplicacion\Nucleo\BaseDeDatos; 

final class RepositorioUsuarios 
{
    /**
     * Busca un usuario por su código (ej. USR001). Devuelve null si no existe.
     */
    public function buscarUsuarioPorCodigo(string $codigoDelUsuario): ?array
    {
        $conexion = BaseDeDatos::obtenerConexion(); 
        $sentencia = $conexion->prepare( 
            'SELECT id, codigo, nombre, cargo, area, activo ' . // Columnas que necesita la página
            'FROM usuarios ' . // Tabla de solicitantes
            'WHERE codigo = :codigo' // El comodín :codigo se llena al ejecutar
        );
        $sentencia->execute(['codigo' => $codigoDelUsuario]); // Ejecuta enviando el código como dato, no como parte del SQL
        $filaDelUsuario = $sentencia->fetch(); // Lee la única fila posible (codigo es UNIQUE); false si no hay
        return $filaDelUsuario === false ? null : $filaDelUsuario; // Convierte "no hay fila" en null
    }
}
