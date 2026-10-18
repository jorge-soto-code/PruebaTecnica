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

use Aplicacion\Nucleo\BaseDeDatos; // Para abrir la transacción
use Aplicacion\Nucleo\ExcepcionApi; // Para lanzar 404, 409 y 422
use Aplicacion\Repositorios\RepositorioCategorias; // SQL de catálogos
use Aplicacion\Repositorios\RepositorioConsecutivos; // Número de ticket
use Aplicacion\Repositorios\RepositorioHistorial; // SQL del historial
use Aplicacion\Repositorios\RepositorioTecnicos; // SQL de técnicos
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
    private RepositorioTecnicos $repositorioDeTecnicos; // Consultas SQL de técnicos (bloqueo y conteo)
    private RepositorioConsecutivos $repositorioDeConsecutivos; // Genera el número de ticket
    private ServicioFlujoDeEstados $servicioDeFlujoDeEstados; // Regla ABIERTO -> EN CURSO -> CERRADO

    public function __construct() // Se ejecuta al crear el servicio con "new ServicioTickets()"
    {
        $this->repositorioDeTickets = new RepositorioTickets(); // Crea el repositorio de tickets
        $this->repositorioDeHistorial = new RepositorioHistorial(); // Crea el repositorio del historial
        $this->repositorioDeUsuarios = new RepositorioUsuarios(); // Crea el repositorio de usuarios
        $this->repositorioDeCategorias = new RepositorioCategorias(); // Crea el repositorio de categorías
        $this->servicioDeSla = new ServicioSla(); // Crea el servicio de SLA
        $this->servicioDeTecnicos = new ServicioTecnicos(); // Crea el servicio de técnicos
        $this->repositorioDeTecnicos = new RepositorioTecnicos(); // Crea el repositorio de técnicos
        $this->repositorioDeConsecutivos = new RepositorioConsecutivos(); // Crea el repositorio de consecutivos
        $this->servicioDeFlujoDeEstados = new ServicioFlujoDeEstados(); // Crea la regla del flujo de estados
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

    // FASE 5 
    /**
     * Cambia el estado de un ticket respetando el flujo ABIERTO -> EN CURSO -> CERRADO.
     * Lanza 404 si no existe y 409 si el salto no es válido. Devuelve el detalle actualizado.
     */
    public function cambiarEstadoDelTicket(string $numeroDelTicket, array $datos): array
    {
        $ahoraParaLaBase = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'); // Momento del cambio (para el cierre y el historial)
        $this->ejecutarEnTransaccion(function () use ($numeroDelTicket, $datos, $ahoraParaLaBase): void { // Todo lo de adentro se guarda junto o no se guarda nada
            $ticket = $this->repositorioDeTickets->bloquearTicketPorNumero($numeroDelTicket); // Bloquea el ticket: nadie más lo cambia mientras decidimos
            if ($ticket === null) { // Si el número no existe
                throw new ExcepcionApi('NO_ENCONTRADO', 'El ticket ' . $numeroDelTicket . ' no existe', 404); // Responde 404
            }

            $estadoActual = $ticket['estado']; // Estado en el que está ahora
            $estadoNuevo = $datos['estado']; // Estado que se pide

            if (!$this->servicioDeFlujoDeEstados->transicionEsValida($estadoActual, $estadoNuevo)) { // Si no es el siguiente paso exacto
                throw new ExcepcionApi('TRANSICION_INVALIDA', $this->servicioDeFlujoDeEstados->obtenerMensajeDeTransicionInvalida($estadoActual, $estadoNuevo), 409); // Responde 409
            }

            $seCierra = $estadoNuevo === 'CERRADO'; // Solo al cerrar se guardan solución y fecha de cierre
            $this->repositorioDeTickets->actualizarEstadoDelTicket( // Guarda el nuevo estado
                (int) $ticket['id'], // Ticket a modificar
                $estadoNuevo, // Estado nuevo
                $seCierra ? $datos['solucion'] : null, // Solución (solo al cerrar)
                $seCierra ? $ahoraParaLaBase : null // Fecha de cierre (solo al cerrar)
            );

            $observacion = $datos['observacion'] ?? 'Cambio de estado de ' . $estadoActual . ' a ' . $estadoNuevo; // Usa la nota enviada o una automática
            $this->repositorioDeHistorial->registrarCambioEnElHistorial((int) $ticket['id'], $estadoActual, $estadoNuevo, $observacion, $ahoraParaLaBase); // Deja el cambio en el historial
        });

        return $this->obtenerDetalleDelTicket($numeroDelTicket); // Devuelve el ticket ya actualizado
    }
    /**
     * Reasigna el ticket a otro técnico. Lanza 404 si no existe, 409 si el ticket está cerrado,
     * ya es de ese técnico o el técnico está al tope, y 422 si el técnico no existe o está inactivo.
     */
    public function reasignarTecnicoDelTicket(string $numeroDelTicket, array $datos): array
    {
        $ahoraParaLaBase = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'); // Momento del cambio (para el historial)

        $this->ejecutarEnTransaccion(function () use ($numeroDelTicket, $datos, $ahoraParaLaBase): void { // Todo lo de adentro se guarda junto o no se guarda nada
            $ticket = $this->repositorioDeTickets->bloquearTicketPorNumero($numeroDelTicket); // Bloquea el ticket
            if ($ticket === null) { // Si el número no existe
                throw new ExcepcionApi('NO_ENCONTRADO', 'El ticket ' . $numeroDelTicket . ' no existe', 404); // Responde 404
            }

            if ($ticket['estado'] === 'CERRADO') { // Un ticket cerrado ya no se reasigna
                throw new ExcepcionApi('TICKET_CERRADO', 'No se puede reasignar un ticket CERRADO', 409); // Responde 409
            }

            $idDelTecnicoNuevo = $datos['tecnico_id']; // Técnico al que se quiere pasar
            if ($idDelTecnicoNuevo === (int) $ticket['tecnico_id']) { // Si ya es de ese técnico
                throw new ExcepcionApi('MISMO_TECNICO', 'El ticket ya está asignado a ese técnico', 409); // Responde 409
            }

            $tecnicoNuevo = $this->repositorioDeTecnicos->bloquearTecnicoPorId($idDelTecnicoNuevo); // Bloquea al técnico nuevo: evita que dos personas le den el 5.º ticket a la vez
            if ($tecnicoNuevo === null || (int) $tecnicoNuevo['activo'] !== 1) { // Si no existe o está inactivo
                throw new ExcepcionApi('VALIDACION_FALLIDA', 'El técnico no está disponible', 422, ['tecnico_id' => 'El técnico no existe o está inactivo']); // Responde 422
            }

            $cargaActual = $this->repositorioDeTecnicos->contarTicketsActivosDelTecnico($idDelTecnicoNuevo); // Tickets ABIERTOS o EN CURSO que ya tiene
            if (!$this->servicioDeTecnicos->tecnicoPuedeRecibirOtroTicket($cargaActual)) { // Si ya está en el tope
                throw new ExcepcionApi('TOPE_CARGA', 'El técnico ' . $tecnicoNuevo['nombre'] . ' ya tiene el máximo de tickets activos', 409); // Responde 409
            }

            $tecnicoAnterior = $this->repositorioDeTecnicos->buscarTecnicoPorId((int) $ticket['tecnico_id']); // Técnico actual (solo para escribir su nombre en el historial)
            $nombreAnterior = $tecnicoAnterior['nombre'] ?? 'sin técnico'; // Nombre del técnico anterior

            $this->repositorioDeTickets->actualizarTecnicoDelTicket((int) $ticket['id'], $idDelTecnicoNuevo); // Guarda el técnico nuevo

            $observacion = 'Reasignado de ' . $nombreAnterior . ' a ' . $tecnicoNuevo['nombre']; // Nota automática del cambio
            if ($datos['observacion'] !== null) { // Si la persona escribió un motivo
                $observacion .= '. ' . $datos['observacion']; // Lo agrega al final
            }

            $this->repositorioDeHistorial->registrarCambioEnElHistorial((int) $ticket['id'], $ticket['estado'], $ticket['estado'], $observacion, $ahoraParaLaBase); // El estado no cambia: anterior y nuevo son iguales
        });

        return $this->obtenerDetalleDelTicket($numeroDelTicket); // Devuelve el ticket ya actualizado
    }
    /**
     * Ejecuta una función dentro de una transacción: si todo sale bien confirma, y si algo falla deshace todo.
     * Devuelve lo que devuelva la función.
     */
    private function ejecutarEnTransaccion(callable $trabajo): mixed
    {
        $conexion = BaseDeDatos::obtenerConexion(); // Conexión compartida: la transacción vive en ella
        $conexion->beginTransaction(); // Empieza la transacción: o se guarda todo o no se guarda nada

        try { 
            $resultado = $trabajo(); // Hace el trabajo pedido
            $conexion->commit(); // Todo salió bien: guarda definitivamente y libera los bloqueos
            return $resultado; // Entrega lo que devolvió el trabajo
        } catch (\Throwable $error) { // Cualquier fallo (regla de negocio o error de base de datos)
            $conexion->rollBack(); // Deshace todo lo hecho dentro de la transacción
            throw $error; // Vuelve a lanzar el error para que el manejador global responda
        }
    }
    // FASE 3
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

    // FASE 4 
    /**
     * Crea un ticket con todas las reglas de la prueba y devuelve su detalle.
     * Recibe los datos ya limpios por ValidadorTickets.
     */
    public function crearTicket(array $datos): array
    {
        $usuario = $this->repositorioDeUsuarios->buscarUsuarioPorCodigo($datos['codigo_usuario']); // Busca al solicitante
        if ($usuario === null) { // Si el código no existe
            throw new ExcepcionApi('VALIDACION_FALLIDA', 'El usuario no existe', 422, ['codigo_usuario' => 'No existe un usuario con ese código']); // No se permite registrar
        }
        if ((int) $usuario['activo'] !== 1) { // Si el usuario está inactivo
            throw new ExcepcionApi('VALIDACION_FALLIDA', 'El usuario está inactivo', 422, ['codigo_usuario' => 'El usuario está inactivo y no puede registrar tickets']); // No se permite registrar
        }

        if ($this->repositorioDeCategorias->buscarCategoriaPorId($datos['categoria_id']) === null) { // Si la categoría no existe
            throw new ExcepcionApi('VALIDACION_FALLIDA', 'La categoría no existe', 422, ['categoria_id' => 'No existe una categoría con ese id']); // Responde 422
        }

        $prioridad = $this->repositorioDeCategorias->buscarPrioridadPorNombre($datos['prioridad']); // Busca la prioridad y sus horas de SLA
        if ($prioridad === null) { // Si no existe en la tabla
            throw new ExcepcionApi('VALIDACION_FALLIDA', 'La prioridad no existe', 422, ['prioridad' => 'No existe esa prioridad']); // Responde 422
        }

        $ahora = new DateTimeImmutable('now'); // Momento exacto de creación (se usa para la fecha, el límite y el historial)
        $fechaLimite = $this->servicioDeSla->calcularFechaLimite($ahora, (int) $prioridad['horas_sla']); // Creación + horas del SLA
        $ahoraParaLaBase = $ahora->format('Y-m-d H:i:s'); // Formato que entiende MySQL

        $conexion = BaseDeDatos::obtenerConexion(); 
        $conexion->beginTransaction(); // Empieza la transacción: o se guarda todo o no se guarda nada

        try { // Si algo falla dentro, se deshace todo
            $tecnico = $this->repositorioDeTecnicos->bloquearTecnicoPorId($datos['tecnico_id']); // Bloquea al técnico: nadie más puede asignarle tickets mientras decidimos
            if ($tecnico === null || (int) $tecnico['activo'] !== 1) { // Si no existe o está inactivo
                throw new ExcepcionApi('VALIDACION_FALLIDA', 'El técnico no está disponible', 422, ['tecnico_id' => 'El técnico no existe o está inactivo']); // Responde 422
            }

            $cargaActual = $this->repositorioDeTecnicos->contarTicketsActivosDelTecnico($datos['tecnico_id']); // Tickets ABIERTOS o EN CURSO que ya tiene
            if (!$this->servicioDeTecnicos->tecnicoPuedeRecibirOtroTicket($cargaActual)) { // Si ya está en el tope
                throw new ExcepcionApi('TOPE_CARGA', 'El técnico ' . $tecnico['nombre'] . ' ya tiene el máximo de tickets activos', 409); // Responde 409
            }

            $numeroDelTicket = $this->repositorioDeConsecutivos->generarSiguienteNumeroDeTicket((int) $ahora->format('Y')); // Número único, seguro ante concurrencia
            $idDelTicket = $this->repositorioDeTickets->insertarTicket([ // Guarda el ticket
                'numero'         => $numeroDelTicket, // TK-2026-000026
                'usuario_id'     => (int) $usuario['id'], // Solicitante
                'categoria_id'   => $datos['categoria_id'], // Categoría
                'prioridad_id'   => (int) $prioridad['id'], // Prioridad
                'tecnico_id'     => $datos['tecnico_id'], // Técnico asignado
                'descripcion'    => $datos['descripcion'], // Descripción
                'fecha_creacion' => $ahoraParaLaBase, // Momento de creación
                'fecha_limite'   => $fechaLimite->format('Y-m-d H:i:s'), // Fecha límite del SLA
            ]);

            $this->repositorioDeHistorial->registrarCambioEnElHistorial( // Primera línea del historial
                $idDelTicket, // Ticket recién creado
                null, // No había estado anterior
                'ABIERTO', // Estado inicial
                'Ticket creado y asignado a ' . $tecnico['nombre'], // Observación
                $ahoraParaLaBase // Fecha del registro
            );

            $conexion->commit(); // Todo salió bien: guarda definitivamente y libera los bloqueos
        } catch (\Throwable $errorAlCrear) { // Cualquier fallo (regla de negocio o error de base de datos)
            $conexion->rollBack(); // Deshace todo lo hecho dentro de la transacción (incluido el consecutivo)
            throw $errorAlCrear; // Vuelve a lanzar el error para que el manejador global responda
        }

        return $this->obtenerDetalleDelTicket($numeroDelTicket); // Devuelve el ticket recién creado con el mismo formato del detalle
    }
}
