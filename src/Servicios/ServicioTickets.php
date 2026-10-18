<?php
/**
 * ARCHIVO: src/Servicios/ServicioTickets.php
 * CAPA: Lógica de negocio (Servicios) — el "cerebro" del sistema
 * FASE: 3 (lectura), 4 (crear), 5 (estados y reasignación)
 *
 * QUÉ VA AQUÍ:
 *   Todas las reglas de la prueba, en un solo lugar:
 *     - (Fase 3, ya programado) listar tickets, detalle, usuario, catálogos y resumen del tablero.
 *     - (Fase 4) crearTicket(): valida usuario activo, técnico activo, tope de carga,
 *       genera el número, calcula la fecha límite (SLA), guarda el ticket y
 *       su primera línea de historial. TODO dentro de una transacción.
 *     - cambiarEstadoDelTicket(): solo ABIERTO -> EN CURSO -> CERRADO. Lanza
 *       TRANSICION_INVALIDA (409) si se salta un paso o se reabre. Para
 *       cerrar exige la solución. Guarda historial.
 *     - reasignarTecnicoDelTicket(): valida que el técnico esté activo y que
 *       no esté en el tope de 5 (409). Guarda historial.
 *
 * POR QUÉ UN SOLO LUGAR:
 *   Si las reglas estuvieran repartidas, serían difíciles de explicar en el
 *   video y de probar con PHPUnit. Aquí están juntas.
 *
 * QUÉ NO VA AQUÍ:
 *   SQL (usa los Repositorios) ni código HTTP (eso es del Controlador).
 */
declare(strict_types=1); 
namespace Aplicacion\Servicios; 

use Aplicacion\Nucleo\ExcepcionApi; // Para lanzar 404
use Aplicacion\Repositorios\RepositorioCategorias; // SQL de catálogos
use Aplicacion\Repositorios\RepositorioHistorial; // SQL del historial
use Aplicacion\Repositorios\RepositorioTickets; // SQL de tickets
use Aplicacion\Repositorios\RepositorioUsuarios; // SQL de usuarios
use DateTimeImmutable; // Fecha y hora que no se modifica por accidente

final class ServicioTickets // "final": ninguna otra clase puede heredar de esta
{
    private RepositorioTickets $repositorioDeTickets; // Consultas SQL de tickets
    private RepositorioHistorial $repositorioDeHistorial; // Consultas SQL del historial
    private RepositorioUsuarios $repositorioDeUsuarios; // Consultas SQL de usuarios
    private RepositorioCategorias $repositorioDeCategorias; // Consultas SQL de categorías y prioridades
    private ServicioSla $servicioDeSla; // Cálculo del indicador de SLA
    private ServicioTecnicos $servicioDeTecnicos; // Reglas de técnicos (carga y tope)

    public function __construct() // Se ejecuta al crear el servicio con "new ServicioTickets()"
    {
        $this->repositorioDeTickets = new RepositorioTickets(); // Crea el repositorio de tickets
        $this->repositorioDeHistorial = new RepositorioHistorial(); // Crea el repositorio del historial
        $this->repositorioDeUsuarios = new RepositorioUsuarios(); // Crea el repositorio de usuarios
        $this->repositorioDeCategorias = new RepositorioCategorias(); // Crea el repositorio de categorías
        $this->servicioDeSla = new ServicioSla(); // Crea el servicio de SLA
        $this->servicioDeTecnicos = new ServicioTecnicos(); // Crea el servicio de técnicos
    }

    /**
     * Busca un usuario por su código. Lanza 404 si no existe.
     */
    public function obtenerUsuarioPorCodigo(string $codigoDelUsuario): array
    {
        $filaDelUsuario = $this->repositorioDeUsuarios->buscarUsuarioPorCodigo($codigoDelUsuario); // Pregunta a la base de datos
        if ($filaDelUsuario === null) { // Si el código no existe
            throw new ExcepcionApi('NO_ENCONTRADO', 'El usuario ' . $codigoDelUsuario . ' no existe', 404); // Responde 404
        }

        return [ // Entrega los datos con el formato de la API
            'codigo' => $filaDelUsuario['codigo'], // Código del usuario
            'nombre' => $filaDelUsuario['nombre'], // Nombre completo
            'cargo'  => $filaDelUsuario['cargo'], // Cargo
            'area'   => $filaDelUsuario['area'], // Área
            'activo' => (bool) $filaDelUsuario['activo'], // true/false (la BD guarda 1/0)
        ];
    }

    /**
     * Categorías y prioridades para llenar las listas de la página.
     */
    public function listarCategoriasYPrioridades(): array
    {
        return [ // Entrega ambos catálogos juntos
            'categorias'  => $this->repositorioDeCategorias->listarCategorias(), // Hardware, Red, Software, Otro
            'prioridades' => $this->repositorioDeCategorias->listarPrioridades(), // Alta, Media, Baja con sus horas de SLA
        ];
    }

    /**
     * Lista de tickets de una página, con el total para paginar.
     */
    public function listarTickets(array $filtros, int $pagina, int $registrosPorPagina): array
    {
        $resultado = $this->repositorioDeTickets->buscarTicketsPaginados($filtros, $pagina, $registrosPorPagina); // Pide la página a la base de datos
        $momentoActual = new DateTimeImmutable('now'); // Hora de "ahora" para calcular el SLA
        $ticketsParaLaApi = []; // Aquí se arma la lista final

        foreach ($resultado['filas'] as $fila) { // Recorre cada ticket de la página
            $ticketsParaLaApi[] = $this->convertirFilaDeTicket($fila, $momentoActual); // Lo convierte al formato de la API
        }

        return [ // Entrega la lista y los datos de paginación
            'data' => $ticketsParaLaApi, // Tickets de esta página
            'meta' => [ // Datos para dibujar los botones de paginación
                'page'     => $pagina, // Página actual
                'per_page' => $registrosPorPagina, // Registros por página
                'total'    => $resultado['total'], // Total de tickets que cumplen los filtros
            ],
        ];
    }

    /**
     * Detalle de un ticket con su historial. Lanza 404 si no existe.
     */
    public function obtenerDetalleDelTicket(string $numeroDelTicket): array
    {
        $fila = $this->repositorioDeTickets->buscarTicketPorNumero($numeroDelTicket); // Pregunta a la base de datos
        if ($fila === null) { // Si el número no existe
            throw new ExcepcionApi('NO_ENCONTRADO', 'El ticket ' . $numeroDelTicket . ' no existe', 404); // Responde 404
        }

        $ticket = $this->convertirFilaDeTicket($fila, new DateTimeImmutable('now')); // Formato básico (igual que en la lista)

        $ticket['codigo_usuario'] = $fila['codigo_usuario']; // Código del solicitante
        $ticket['cargo'] = $fila['cargo']; // Cargo del solicitante
        $ticket['area'] = $fila['area']; // Área del solicitante
        $ticket['descripcion'] = $fila['descripcion']; // Qué se pidió
        $ticket['solucion'] = $fila['solucion']; // Solución aplicada (null si no está cerrado)
        $ticket['cerrado'] = $fila['fecha_cierre'] === null ? null : $this->formatearFecha($fila['fecha_cierre']); // Fecha de cierre o null

        $ticket['historial'] = []; // Lista de cambios del ticket

        foreach ($this->repositorioDeHistorial->listarHistorialDelTicket((int) $fila['id']) as $cambio) { // Recorre cada cambio
            $ticket['historial'][] = [ // Agrega el cambio con el formato de la API
                'fecha'           => $this->formatearFecha($cambio['fecha_creacion']), // Cuándo ocurrió
                'estado_anterior' => $cambio['estado_anterior'], // Estado antes (null en el primer registro)
                'estado_nuevo'    => $cambio['estado_nuevo'], // Estado después
                'observacion'     => $cambio['observacion'], // Nota del cambio
            ];
        }
        return $ticket; // Entrega el detalle completo
    }

    /**
     * Números del tablero: tickets por estado, vencidos y carga por técnico.
     */
    public function obtenerResumenDelTablero(): array
    {
        $cantidadesPorEstado = $this->repositorioDeTickets->contarTicketsPorEstado(); // Ej. ['ABIERTO' => 6, ...]
        $porEstado = []; // Resultado con los tres estados siempre presentes

        foreach (['ABIERTO', 'EN CURSO', 'CERRADO'] as $estado) { // Recorre los tres estados
            $porEstado[$estado] = $cantidadesPorEstado[$estado] ?? 0; // Si no hay tickets en ese estado, muestra 0
        }
        $momentoActual = new DateTimeImmutable('now'); // Hora de "ahora"

        return [ // Entrega el resumen
            'total'            => array_sum($porEstado), // Total de tickets
            'por_estado'       => $porEstado, // Tickets en cada estado
            'vencidos'         => $this->repositorioDeTickets->contarTicketsVencidos($momentoActual->format('Y-m-d H:i:s')), // No cerrados con fecha límite pasada
            'carga_por_tecnico' => $this->servicioDeTecnicos->listarTecnicosActivosConCarga(), // Carga de cada técnico activo
        ];
    }

    /**
     * Convierte una fila de la base de datos al formato de la API (el que pide la prueba).
     */
    private function convertirFilaDeTicket(array $fila, DateTimeImmutable $momentoActual): array
    {
        $fechaDeCreacion = new DateTimeImmutable($fila['fecha_creacion']); // Texto de la BD convertido a fecha
        $fechaLimite = new DateTimeImmutable($fila['fecha_limite']); // Texto de la BD convertido a fecha

        return [ // Arma el ticket con los nombres que exige la prueba
            'numero'        => $fila['numero'], // TK-2026-000018
            'usuario'       => $fila['usuario'], // Nombre del solicitante
            'categoria'     => $fila['categoria'], // Hardware, Software, Red u Otro
            'prioridad'     => $fila['prioridad'], // Alta, Media o Baja
            'tecnico'       => $fila['tecnico'], // Técnico asignado
            'estado'        => $fila['estado'], // ABIERTO, EN CURSO o CERRADO
            'creado'        => $fechaDeCreacion->format('c'), // Fecha ISO 8601, ej. 2026-10-16T09:30:00-05:00
            'vence'         => $fechaLimite->format('c'), // Fecha límite en el mismo formato
            'indicador_sla' => $this->servicioDeSla->calcularIndicadorDeSla( // VENCIDO, POR_VENCER, EN_TIEMPO o null
                $fila['estado'], // Estado actual
                $fechaDeCreacion, // Cuándo se creó
                $fechaLimite, // Fecha límite
                $momentoActual // Hora de ahora
            ),
        ];
    }

    /**
     * Convierte una fecha de la BD ("2026-10-16 09:30:00") a ISO 8601 con zona horaria.
     */
    private function formatearFecha(string $fechaDeLaBaseDeDatos): string
    {
        return (new DateTimeImmutable($fechaDeLaBaseDeDatos))->format('c'); // Ej. 2026-10-16T09:30:00-05:00
    }
}
