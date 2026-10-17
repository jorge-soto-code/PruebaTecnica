<?php
declare(strict_types=1);
namespace Aplicacion\Nucleo; 
use PDO; 
final class BaseDeDatos 
{
    private static ?PDO $conexionCompartida = null;
    public static function obtenerConexion(): PDO
    {
        if (self::$conexionCompartida === null) {       
            $cadenaDeConexion = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s', 
                Configuracion::obtenerObligatorio('base_datos.servidor'), 
                (int) Configuracion::obtenerObligatorio('base_datos.puerto'), 
                Configuracion::obtenerObligatorio('base_datos.nombre'), 
                Configuracion::obtener('base_datos.juego_caracteres', 'utf8mb4') 
            );
            $desfaseHorarioDePhp = (new \DateTimeImmutable())->format('P');

            self::$conexionCompartida = new PDO( 
                $cadenaDeConexion, 
                (string) Configuracion::obtenerObligatorio('base_datos.usuario'), 
                (string) Configuracion::obtener('base_datos.clave', ''), 
                [ // Opciones de la conexión
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, 
                    PDO::ATTR_EMULATE_PREPARES   => false, 
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '$desfaseHorarioDePhp'", 
                ]
            );
        }
        return self::$conexionCompartida;
    }
}
