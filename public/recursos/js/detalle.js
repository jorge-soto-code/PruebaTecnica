/*
  ARCHIVO: public/recursos/js/detalle.js
  FASE: 6

  QUÉ VA AQUÍ:
    La ventana de detalle de un ticket (se abre al hacer clic en una fila):
      - Datos completos del ticket, fecha límite del SLA y su indicador.
      - Historial completo: fecha y hora, estado anterior, estado nuevo y
        observación de cada cambio.
      - Botón para avanzar el estado (ABIERTO -> EN CURSO -> CERRADO). Al
        cerrar pide la solución aplicada (obligatoria).
      - Selector para reasignar el técnico.
      - Si la API responde 409 (transición inválida o técnico al tope), muestra
        el mensaje del error.
      - Después de cada cambio, refresca el detalle, la tabla y el tablero.
*/

const estadoDelDetalle = { // Lo que la ventana de detalle recuerda
    ventana: null, // La ventana emergente (modal) de Bootstrap
    ticket: null, // El ticket que se está viendo
    tecnicos: [], // Técnicos activos con su carga (para el selector de reasignar)
};

function obtenerVentanaDeDetalle() { // Devuelve la ventana emergente, creándola la primera vez
    if (estadoDelDetalle.ventana === null) { // Si todavía no existe
        estadoDelDetalle.ventana = new bootstrap.Modal(document.getElementById('modal-detalle')); // La crea con Bootstrap
    }
    return estadoDelDetalle.ventana; // La devuelve
}

function mostrarErrorDeDetalle(mensaje) { // Muestra un error dentro de la ventana de detalle
    const zona = document.getElementById('error-detalle'); // Zona de errores del detalle
    zona.textContent = mensaje; // Escribe el mensaje como texto
    zona.classList.remove('d-none'); // La hace visible
}

function ocultarErrorDeDetalle() { // Oculta el error anterior
    const zona = document.getElementById('error-detalle'); // Zona de errores del detalle
    zona.textContent = ''; // La vacía
    zona.classList.add('d-none'); // La oculta
}

function crearDatoDelTicket(titulo, valor) { // Una pareja "título: valor" para la ficha del ticket
    const columna = crearElemento('div', 'col-6 col-md-4'); // Columna de la ficha
    columna.append(crearElemento('div', 'text-muted small', titulo)); // Título pequeño
    columna.append(crearElemento('div', 'fw-semibold', valor || '—')); // Valor (guion si está vacío)
    return columna; // Devuelve la columna lista
}

function crearTablaDeHistorial(historial) { // Tabla con todos los cambios del ticket
    const tabla = crearElemento('table', 'table table-sm align-middle mb-0'); // Tabla de Bootstrap
    const encabezado = crearElemento('thead'); // Cabecera
    const filaDeTitulos = crearElemento('tr'); // Fila de títulos
    for (const titulo of ['Fecha y hora', 'Estado anterior', 'Estado nuevo', 'Observación']) { // Las cuatro columnas que pide la prueba
        filaDeTitulos.append(crearElemento('th', '', titulo)); // Agrega cada título
    }
    encabezado.append(filaDeTitulos); // Mete los títulos en la cabecera

    const cuerpo = crearElemento('tbody'); // Cuerpo de la tabla
    for (const cambio of historial) { // Recorre cada cambio, del más antiguo al más nuevo
        const fila = crearElemento('tr'); // Una fila por cambio
        fila.append(crearElemento('td', 'text-nowrap', formatearFecha(cambio.fecha))); // Cuándo ocurrió
        fila.append(crearElemento('td', '', cambio.estado_anterior || '—')); // Estado antes (guion en el primer registro)
        fila.append(crearElemento('td', '', cambio.estado_nuevo)); // Estado después
        fila.append(crearElemento('td', '', cambio.observacion || '—')); // Nota del cambio
        cuerpo.append(fila); // La agrega al cuerpo
    }

    tabla.append(encabezado, cuerpo); // Une cabecera y cuerpo
    return tabla; // Devuelve la tabla lista
}

function crearCampoDeTexto(etiqueta, id, esLargo) { // Un campo (texto o área de texto) con su etiqueta
    const grupo = crearElemento('div', 'mb-2'); // Contenedor del campo
    const rotulo = crearElemento('label', 'form-label small mb-1', etiqueta); // Etiqueta
    rotulo.htmlFor = id; // La une con el campo
    const campo = crearElemento(esLargo ? 'textarea' : 'input', 'form-control form-control-sm'); // Área de texto o línea de texto
    campo.id = id; // Le pone su id
    if (esLargo) { // Si es un área de texto
        campo.rows = 2; // Dos líneas de alto
    } else { // Si es una línea
        campo.type = 'text'; // Tipo texto
    }
    grupo.append(rotulo, campo); // Mete etiqueta y campo en el contenedor
    return grupo; // Devuelve el contenedor listo
}

function crearBotonDeAccion(texto, clases, alHacerClic) { // Un botón de acción dentro del detalle
    const boton = crearElemento('button', 'btn btn-sm ' + clases, texto); // Botón de Bootstrap
    boton.type = 'button'; // Evita que se comporte como botón de envío
    boton.addEventListener('click', async () => { // Al hacer clic
        for (const otro of document.querySelectorAll('#acciones-detalle button')) { // Todos los botones de acciones
            otro.disabled = true; // Los apaga mientras se envía (evita doble clic)
        }
        ocultarErrorDeDetalle(); // Quita el error anterior
        try { // Intenta hacer la acción
            await alHacerClic(); // Ejecuta lo que corresponde (cambiar estado o reasignar)
        } finally { // Pase lo que pase
            for (const otro of document.querySelectorAll('#acciones-detalle button')) { // Vuelve a revisar los botones
                otro.disabled = false; // Los enciende de nuevo
            }
        }
    });
    return boton; // Devuelve el botón listo
}

async function aplicarCambioEnElDetalle(llamadaALaApi, mensajeDeExito) { // Hace un cambio, y actualiza detalle, tabla y tablero
    try { // Intenta el cambio
        const ticketActualizado = await llamadaALaApi(); // La API responde con el ticket ya actualizado
        estadoDelDetalle.ticket = ticketActualizado; // Guarda el ticket nuevo
        mostrarAviso(mensajeDeExito, 'success'); // Confirma al usuario
        estadoDelDetalle.tecnicos = (await listarTecnicos()).data; // Recarga las cargas de los técnicos
        pintarDetalle(); // Redibuja la ventana con los datos nuevos
        refrescarTodo(); // Actualiza tabla y tablero de atrás (sin esperar)
    } catch (error) { // Si la API rechazó el cambio (409, 422...)
        mostrarErrorDeDetalle(describirErrorDeApi(error)); // Muestra el mensaje de la API dentro de la ventana
        if (error instanceof ErrorDeApi && error.codigo === 'TOPE_CARGA') { // Si el técnico llegó al tope
            try { // Actualiza las cargas del selector
                estadoDelDetalle.tecnicos = (await listarTecnicos()).data; // Pide las cargas nuevas
                pintarDetalle(); // Redibuja con las cargas al día
                mostrarErrorDeDetalle(describirErrorDeApi(error)); // Vuelve a mostrar el error (el redibujado lo borró)
            } catch (errorAlRecargar) { // Si tampoco se pudo
                // No hace nada más: el error principal ya se mostró
            }
        }
    }
}

function crearBloqueDeEstado(ticket) { // Zona para avanzar el estado del ticket
    const bloque = crearElemento('div', 'col-12 col-lg-6'); // Mitad izquierda de las acciones
    bloque.append(crearElemento('h3', 'h6', 'Cambiar estado')); // Título del bloque

    if (ticket.estado === 'ABIERTO') { // Un ticket abierto solo puede pasar a EN CURSO
        bloque.append(crearCampoDeTexto('Observación (opcional)', 'observacion-estado', false)); // Nota del cambio
        bloque.append(crearBotonDeAccion('Pasar a EN CURSO', 'btn-info', () => aplicarCambioEnElDetalle( // Botón de avanzar
            () => cambiarEstado(ticket.numero, { estado: 'EN CURSO', observacion: document.getElementById('observacion-estado').value }), // Llama a la API
            'El ticket ' + ticket.numero + ' pasó a EN CURSO.' // Mensaje de éxito
        )));
    } else { // Un ticket en curso solo puede pasar a CERRADO
        bloque.append(crearCampoDeTexto('Solución aplicada (obligatoria para cerrar)', 'solucion-estado', true)); // Solución
        bloque.append(crearCampoDeTexto('Observación (opcional)', 'observacion-estado', false)); // Nota del cambio
        bloque.append(crearBotonDeAccion('Cerrar ticket', 'btn-success', () => aplicarCambioEnElDetalle( // Botón de cerrar
            () => cambiarEstado(ticket.numero, { // Llama a la API
                estado: 'CERRADO', // Estado nuevo
                solucion: document.getElementById('solucion-estado').value, // Solución escrita
                observacion: document.getElementById('observacion-estado').value, // Nota escrita
            }),
            'El ticket ' + ticket.numero + ' fue cerrado.' // Mensaje de éxito
        )));
    }
    return bloque; // Devuelve el bloque listo
}

function crearBloqueDeTecnico(ticket) { // Zona para reasignar el técnico
    const bloque = crearElemento('div', 'col-12 col-lg-6'); // Mitad derecha de las acciones
    bloque.append(crearElemento('h3', 'h6', 'Reasignar técnico')); // Título del bloque

    const grupo = crearElemento('div', 'mb-2'); // Contenedor del selector
    const rotulo = crearElemento('label', 'form-label small mb-1', 'Nuevo técnico'); // Etiqueta
    rotulo.htmlFor = 'tecnico-reasignar'; // La une con el selector
    const selector = crearElemento('select', 'form-select form-select-sm'); // Selector de técnico
    selector.id = 'tecnico-reasignar'; // Le pone su id
    llenarListaDeTecnicos(selector, estadoDelDetalle.tecnicos, ticket.tecnico); // Lo llena con las cargas y marca el actual
    grupo.append(rotulo, selector); // Mete etiqueta y selector en el contenedor
    bloque.append(grupo); // Mete el contenedor en el bloque

    bloque.append(crearCampoDeTexto('Motivo (opcional)', 'observacion-tecnico', false)); // Nota de la reasignación
    bloque.append(crearBotonDeAccion('Reasignar', 'btn-primary', () => aplicarCambioEnElDetalle( // Botón de reasignar
        () => reasignarTecnico(ticket.numero, { tecnico_id: Number(selector.value) || null, observacion: document.getElementById('observacion-tecnico').value }), // Llama a la API
        'El ticket ' + ticket.numero + ' fue reasignado.' // Mensaje de éxito
    )));
    return bloque; // Devuelve el bloque listo
}

function pintarDetalle() { // Dibuja todo el contenido de la ventana de detalle
    const ticket = estadoDelDetalle.ticket; // El ticket que se está viendo
    document.getElementById('titulo-detalle').textContent = 'Ticket ' + ticket.numero; // Título de la ventana

    const contenido = crearElemento('div'); // Contenedor de todo el contenido

    const insignias = crearElemento('div', 'd-flex gap-2 mb-3'); // Fila de etiquetas
    insignias.append(crearInsigniaDeEstado(ticket.estado), crearInsigniaDeSla(ticket.indicador_sla)); // Estado y SLA
    contenido.append(insignias); // Las agrega

    const ficha = crearElemento('div', 'row g-3 mb-3'); // Ficha con los datos del ticket
    ficha.append(crearDatoDelTicket('Solicitante', ticket.usuario + ' (' + ticket.codigo_usuario + ')')); // Nombre y código
    ficha.append(crearDatoDelTicket('Cargo y área', ticket.cargo + ' — ' + ticket.area)); // Cargo y área
    ficha.append(crearDatoDelTicket('Categoría', ticket.categoria)); // Categoría
    ficha.append(crearDatoDelTicket('Prioridad', ticket.prioridad)); // Prioridad
    ficha.append(crearDatoDelTicket('Técnico asignado', ticket.tecnico)); // Técnico
    ficha.append(crearDatoDelTicket('Creado', formatearFecha(ticket.creado))); // Fecha de creación
    ficha.append(crearDatoDelTicket('Fecha límite (SLA)', formatearFecha(ticket.vence))); // Fecha límite
    ficha.append(crearDatoDelTicket('Cerrado', formatearFecha(ticket.cerrado))); // Fecha de cierre
    contenido.append(ficha); // Agrega la ficha

    contenido.append(crearElemento('h3', 'h6', 'Descripción')); // Título de la descripción
    contenido.append(crearElemento('p', 'texto-con-saltos', ticket.descripcion)); // Descripción (como texto seguro)

    if (ticket.solucion) { // Si el ticket ya tiene solución
        contenido.append(crearElemento('h3', 'h6', 'Solución aplicada')); // Título de la solución
        contenido.append(crearElemento('p', 'texto-con-saltos', ticket.solucion)); // Solución (como texto seguro)
    }

    contenido.append(crearElemento('h3', 'h6', 'Historial')); // Título del historial
    const envoltura = crearElemento('div', 'table-responsive mb-3'); // Permite deslizar la tabla en pantallas pequeñas
    envoltura.append(crearTablaDeHistorial(ticket.historial)); // Tabla del historial
    contenido.append(envoltura); // La agrega

    const acciones = crearElemento('div', 'row g-3 border-top pt-3'); // Zona de acciones
    acciones.id = 'acciones-detalle'; // Id para poder apagar sus botones mientras se envía
    if (ticket.estado === 'CERRADO') { // Un ticket cerrado ya no admite cambios
        acciones.append(crearElemento('div', 'col-12 text-muted', 'Este ticket está CERRADO: no admite cambios de estado ni reasignación.')); // Mensaje
    } else { // Si no está cerrado
        acciones.append(crearBloqueDeEstado(ticket), crearBloqueDeTecnico(ticket)); // Muestra las dos zonas de acción
    }
    contenido.append(acciones); // Agrega las acciones

    document.getElementById('contenido-detalle').replaceChildren(contenido); // Reemplaza el contenido anterior
    ocultarErrorDeDetalle(); // Quita errores anteriores
}

async function abrirDetalle(numeroDelTicket) { // Abre la ventana de detalle de un ticket
    try { // Intenta pedir los datos
        const [ticket, tecnicos] = await Promise.all([obtenerTicket(numeroDelTicket), listarTecnicos()]); // Pide ticket y técnicos al mismo tiempo
        estadoDelDetalle.ticket = ticket; // Guarda el ticket
        estadoDelDetalle.tecnicos = tecnicos.data; // Guarda los técnicos
    } catch (error) { // Si falla (por ejemplo, el ticket ya no existe)
        mostrarAviso(describirErrorDeApi(error), 'danger'); // Muestra el motivo
        return; // No abre la ventana
    }
    pintarDetalle(); // Dibuja el contenido
    obtenerVentanaDeDetalle().show(); // Muestra la ventana
}
