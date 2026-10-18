<?php
/**
 * ARCHIVO: src/Servicios/ServicioTecnicos.php
 * CAPA: Lógica de negocio (Servicios)
 * FASE: 3 y 5 (la Fase 5 lo reutiliza al reasignar)
 *
 * QUÉ VA AQUÍ:
 *   Reglas relacionadas con los técnicos:
 *     - listarTecnicosActivosConCarga(): técnicos activos con su carga
 *       actual, ordenados de menor a mayor carga (el primero es la
 *       sugerencia por defecto).
 *     - tecnicoPuedeRecibirOtroTicket(): responde "¿este técnico puede
 *       recibir otro ticket?" comparando su carga con el tope (5, tomado de
 *       la configuración).
 *   ServicioTickets usa esto al crear un ticket y al reasignar.
 */
declare(strict_types=1); 
namespace Aplicacion\Servicios;

use Aplicacion\Nucleo\Configuracion; 
use Aplicacion\Repositorios\RepositorioTecnicos; 

final class ServicioTecnicos 
{
    private RepositorioTecnicos $repositorioDeTecnicos; 
    public function __construct() 
    {
        $this->repositorioDeTecnicos = new RepositorioTecnicos(); 
    }
    /**
     * Técnicos activos con su carga y si todavía pueden recibir otro ticket.
     */
    public function listarTecnicosActivosConCarga(): array
    {
        $topeDeCarga = $this->obtenerTopeDeCarga(); 
        $tecnicosConCarga = []; 

        foreach ($this->repositorioDeTecnicos->listarTecnicosActivosConCarga() as $fila) { 
            $carga = (int) $fila['carga']; 
            $tecnicosConCarga[] = [ // Agrega el técnico ya con el formato de la API
                'id'                => (int) $fila['id'], 
                'nombre'            => $fila['nombre'],
                'carga_actual'      => $carga, 
                'tope_de_carga'     => $topeDeCarga, 
                'puede_recibir_mas' => $this->tecnicoPuedeRecibirOtroTicket($carga), 
            ];
        }
        return $tecnicosConCarga; 
    }
    /**
     * Responde si un técnico con esa carga puede recibir otro ticket.
     */
    public function tecnicoPuedeRecibirOtroTicket(int $cargaActual): bool
    {
        return $cargaActual < $this->obtenerTopeDeCarga(); 
    }
    /**
     * Lee el tope de la configuración (por defecto 5).
     */
    private function obtenerTopeDeCarga(): int
    {
        return (int) Configuracion::obtener('maximo_tickets_activos_por_tecnico', 5); 
    }
}
