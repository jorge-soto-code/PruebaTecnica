<?php
/**
 * ARCHIVO: src/Servicios/ServicioSla.php
 * CAPA: Lógica de negocio (Servicios)
 * FASE: 3 (indicador) y 4 (fecha límite)
 *
 * QUÉ VA AQUÍ:
 *   Los cálculos del Acuerdo de Nivel de Servicio (SLA):
 *     - (Fase 4) calcularFechaLimite(fechaDeCreacion, horasDeSla): fecha límite =
 *       creación + horas de la prioridad (Alta 4h, Media 24h, Baja 72h; las
 *       horas salen de la tabla "prioridades").
 *     - calcularIndicadorDeSla(ticket, momentoActual): devuelve "VENCIDO",
 *       "POR_VENCER" o "EN_TIEMPO".
 *           * Cerrado                      -> sin indicador
 *           * ahora > fecha límite         -> VENCIDO
 *           * queda < 25% del tiempo total -> POR_VENCER
 *           * en otro caso                 -> EN_TIEMPO
 *
 * POR QUÉ ES UNA CLASE APARTE:
 *   Es una regla pura (no toca la BD), por eso es muy fácil de probar con
 *   PHPUnit (punto extra de la prueba).
 */
declare(strict_types=1); 
namespace Aplicacion\Servicios; 

use DateTimeImmutable; 
final class ServicioSla 
{
    private const FRACCION_PARA_POR_VENCER = 0.25;
    /**
     * Devuelve "VENCIDO", "POR_VENCER" o "EN_TIEMPO". Para tickets cerrados devuelve null (no aplica).
     */
    public function calcularIndicadorDeSla(
        string $estado, 
        DateTimeImmutable $fechaDeCreacion, 
        DateTimeImmutable $fechaLimite, 
        DateTimeImmutable $momentoActual 
    ): ?string {
        if ($estado === 'CERRADO') { 
            return null; 
        }

        if ($momentoActual > $fechaLimite) { 
            return 'VENCIDO'; 
        }
        $segundosTotales = $fechaLimite->getTimestamp() - $fechaDeCreacion->getTimestamp(); // Tiempo total que se le dio al ticket
        $segundosRestantes = $fechaLimite->getTimestamp() - $momentoActual->getTimestamp(); // Tiempo que todavía le queda

        if ($segundosRestantes < $segundosTotales * self::FRACCION_PARA_POR_VENCER) { // Le queda menos del 25% del tiempo
            return 'POR_VENCER'; 
        }
        return 'EN_TIEMPO'; 
    }

    // FASE 4
    /**
     * Fecha límite = momento de creación + las horas de SLA de la prioridad (Alta 4, Media 24, Baja 72).
     */
    public function calcularFechaLimite(DateTimeImmutable $fechaDeCreacion, int $horasDeSla): DateTimeImmutable
    {
        return $fechaDeCreacion->modify('+' . $horasDeSla . ' hours'); // Suma las horas y devuelve una fecha nueva
    }
}
