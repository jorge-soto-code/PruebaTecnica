<?php
declare(strict_types=1); 

namespace Aplicacion\Servicios; 

final class ServicioFlujoDeEstados 
{
    // Para cada estado actual, el ÚNICO estado al que puede pasar (CERRADO no tiene siguiente)
    private const TRANSICIONES_PERMITIDAS = [
        'ABIERTO'  => 'EN CURSO', 
        'EN CURSO' => 'CERRADO', 
    ];
    /**
     * Responde si un ticket puede pasar de un estado a otro.
     */
    public function transicionEsValida(string $estadoActual, string $estadoNuevo): bool
    {
        return (self::TRANSICIONES_PERMITIDAS[$estadoActual] ?? null) === $estadoNuevo; 
    }
    /**
     * Mensaje claro para el cliente cuando la transición no es válida.
     */
    public function obtenerMensajeDeTransicionInvalida(string $estadoActual, string $estadoNuevo): string
    {
        if ($estadoActual === 'CERRADO') { 
            return 'Un ticket CERRADO no puede reabrirse ni cambiar de estado'; 
        }
        if ($estadoActual === $estadoNuevo) { // Pidió el mismo estado en el que ya está
            return 'El ticket ya está en estado ' . $estadoActual; 
        }
        return 'Un ticket ' . $estadoActual . ' no puede pasar a ' . $estadoNuevo; // Ej. "Un ticket ABIERTO no puede pasar a CERRADO"
    }
}
