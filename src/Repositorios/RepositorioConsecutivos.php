<?php
declare(strict_types=1); 
namespace Aplicacion\Repositorios; 
use Aplicacion\Nucleo\BaseDeDatos; 

final class RepositorioConsecutivos 
{
    /**
     * Devuelve el siguiente número de ticket del año, por ejemplo TK-2026-000042.
     * IMPORTANTE: debe llamarse DENTRO de una transacción abierta por quien lo usa.
     * El bloqueo (FOR UPDATE) dura hasta que esa transacción termina.
     */
    public function generarSiguienteNumeroDeTicket(int $anio): string
    {
        $conexion = BaseDeDatos::obtenerConexion(); 
        $conexion->prepare( 
            'INSERT IGNORE INTO consecutivos (anio, ultimo_numero) VALUES (:anio, 0)' // Si ya existe no hace nada; si es año nuevo la crea en 0
        )->execute(['anio' => $anio]); // Ejecuta enviando el año como dato
        $sentenciaDeLectura = $conexion->prepare( // Paso 2: lee y BLOQUEA la fila del año
            'SELECT ultimo_numero FROM consecutivos WHERE anio = :anio FOR UPDATE' // Nadie más puede leerla con bloqueo hasta que termine esta transacción
        );
        $sentenciaDeLectura->execute(['anio' => $anio]); 
        $siguienteNumero = (int) $sentenciaDeLectura->fetchColumn() + 1; // Paso 3: el último número usado más uno
        $conexion->prepare( // Paso 4: guarda el nuevo último número
            'UPDATE consecutivos SET ultimo_numero = :numero WHERE anio = :anio' // El WHERE usa la llave primaria
        )->execute(['numero' => $siguienteNumero, 'anio' => $anio]); // Ejecuta enviando los valores como datos
        return sprintf('TK-%d-%06d', $anio, $siguienteNumero); // Paso 5: arma el formato TK-AAAA-NNNNNN (6 dígitos con ceros)
    }
}
