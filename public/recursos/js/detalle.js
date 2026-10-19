const estadoDelDetalle = { 
    ventana: null, 
    ticket: null, 
    tecnicos: [], 
};

function obtenerVentanaDeDetalle() { 
    if (estadoDelDetalle.ventana === null) { 
        estadoDelDetalle.ventana = new bootstrap.Modal(document.getElementById('modal-detalle')); 
    }
    return estadoDelDetalle.ventana; 
}

function mostrarErrorDeDetalle(mensaje) { 
    const zona = document.getElementById('error-detalle'); 
    zona.textContent = mensaje; 
    zona.classList.remove('d-none'); 
}

function ocultarErrorDeDetalle() { 
    const zona = document.getElementById('error-detalle'); 
    zona.textContent = ''; 
    zona.classList.add('d-none'); 
}

function crearDatoDelTicket(titulo, valor) { 
    const columna = crearElemento('div', 'col-6 col-md-4');
    columna.append(crearElemento('div', 'text-muted small', titulo)); 
    columna.append(crearElemento('div', 'fw-semibold', valor || '—')); 
    return columna; 
}

function crearTablaDeHistorial(historial) { 
    const tabla = crearElemento('table', 'table table-sm align-middle mb-0'); 
    const encabezado = crearElemento('thead'); 
    const filaDeTitulos = crearElemento('tr'); 
    for (const titulo of ['Fecha y hora', 'Estado anterior', 'Estado nuevo', 'Observación']) { 
        filaDeTitulos.append(crearElemento('th', '', titulo)); 
    }
    encabezado.append(filaDeTitulos); 

    const cuerpo = crearElemento('tbody'); 
    for (const cambio of historial) { 
        const fila = crearElemento('tr'); 
        fila.append(crearElemento('td', 'text-nowrap', formatearFecha(cambio.fecha))); 
        fila.append(crearElemento('td', '', cambio.estado_anterior || '—')); 
        fila.append(crearElemento('td', '', cambio.estado_nuevo)); 
        fila.append(crearElemento('td', '', cambio.observacion || '—')); 
        cuerpo.append(fila); 
    }

    tabla.append(encabezado, cuerpo); 
    return tabla; 
}

function crearCampoDeTexto(etiqueta, id, esLargo) { 
    const grupo = crearElemento('div', 'mb-2'); 
    const rotulo = crearElemento('label', 'form-label small mb-1', etiqueta); 
    rotulo.htmlFor = id; 
    const campo = crearElemento(esLargo ? 'textarea' : 'input', 'form-control form-control-sm'); 
    campo.id = id; 
    if (esLargo) { 
        campo.rows = 2; 
    } else { 
        campo.type = 'text';
    }
    grupo.append(rotulo, campo);
    return grupo; 
}

function crearBotonDeAccion(texto, clases, alHacerClic) { 
    const boton = crearElemento('button', 'btn btn-sm ' + clases, texto); 
    boton.type = 'button'; 
    boton.addEventListener('click', async () => { 
        for (const otro of document.querySelectorAll('#acciones-detalle button')) { // Todos los botones de acciones
            otro.disabled = true; 
        }
        ocultarErrorDeDetalle(); 
        try { 
            await alHacerClic(); 
        } finally { 
            for (const otro of document.querySelectorAll('#acciones-detalle button')) { 
                otro.disabled = false; 
            }
        }
    });
    return boton; 
}

async function aplicarCambioEnElDetalle(llamadaALaApi, mensajeDeExito) { 
    try { // Intenta el cambio
        const ticketActualizado = await llamadaALaApi(); 
        estadoDelDetalle.ticket = ticketActualizado; 
        mostrarAviso(mensajeDeExito, 'success');
        estadoDelDetalle.tecnicos = (await listarTecnicos()).data; 
        pintarDetalle(); 
        refrescarTodo(); 
    } catch (error) { 
        mostrarErrorDeDetalle(describirErrorDeApi(error)); 
        if (error instanceof ErrorDeApi && error.codigo === 'TOPE_CARGA') { 
            try { 
                estadoDelDetalle.tecnicos = (await listarTecnicos()).data; 
                pintarDetalle(); 
                mostrarErrorDeDetalle(describirErrorDeApi(error)); 
            } catch (errorAlRecargar) { 
                // No hace nada más: el error principal ya se mostró
            }
        }
    }
}

function crearBloqueDeEstado(ticket) { 
    const bloque = crearElemento('div', 'col-12 col-lg-6'); 
    bloque.append(crearElemento('h3', 'h6', 'Cambiar estado')); 

    if (ticket.estado === 'ABIERTO') { 
        bloque.append(crearCampoDeTexto('Observación (opcional)', 'observacion-estado', false)); 
        bloque.append(crearBotonDeAccion('Pasar a EN CURSO', 'btn-info', () => aplicarCambioEnElDetalle( 
            () => cambiarEstado(ticket.numero, { estado: 'EN CURSO', observacion: document.getElementById('observacion-estado').value }), 
            'El ticket ' + ticket.numero + ' pasó a EN CURSO.' 
        )));
    } else { 
        bloque.append(crearCampoDeTexto('Solución aplicada (obligatoria para cerrar)', 'solucion-estado', true)); 
        bloque.append(crearCampoDeTexto('Observación (opcional)', 'observacion-estado', false)); 
        bloque.append(crearBotonDeAccion('Cerrar ticket', 'btn-success', () => aplicarCambioEnElDetalle( 
            () => cambiarEstado(ticket.numero, { 
                estado: 'CERRADO',
                solucion: document.getElementById('solucion-estado').value, 
                observacion: document.getElementById('observacion-estado').value, 
            }),
            'El ticket ' + ticket.numero + ' fue cerrado.' 
        )));
    }
    return bloque; 
}

function crearBloqueDeTecnico(ticket) { 
    const bloque = crearElemento('div', 'col-12 col-lg-6'); 
    bloque.append(crearElemento('h3', 'h6', 'Reasignar técnico'));

    const grupo = crearElemento('div', 'mb-2'); 
    const rotulo = crearElemento('label', 'form-label small mb-1', 'Nuevo técnico'); 
    rotulo.htmlFor = 'tecnico-reasignar'; 
    const selector = crearElemento('select', 'form-select form-select-sm'); 
    selector.id = 'tecnico-reasignar'; 
    llenarListaDeTecnicos(selector, estadoDelDetalle.tecnicos, ticket.tecnico); 
    grupo.append(rotulo, selector);
    bloque.append(grupo); 

    bloque.append(crearCampoDeTexto('Motivo (opcional)', 'observacion-tecnico', false)); 
    bloque.append(crearBotonDeAccion('Reasignar', 'btn-primary', () => aplicarCambioEnElDetalle( 
        () => reasignarTecnico(ticket.numero, { tecnico_id: Number(selector.value) || null, observacion: document.getElementById('observacion-tecnico').value }), 
        'El ticket ' + ticket.numero + ' fue reasignado.' 
    )));
    return bloque; 
}

function pintarDetalle() { 
    const ticket = estadoDelDetalle.ticket; 
    document.getElementById('titulo-detalle').textContent = 'Ticket ' + ticket.numero; 

    const contenido = crearElemento('div'); 

    const insignias = crearElemento('div', 'd-flex gap-2 mb-3'); 
    insignias.append(crearInsigniaDeEstado(ticket.estado), crearInsigniaDeSla(ticket.indicador_sla)); 
    contenido.append(insignias); 

    const ficha = crearElemento('div', 'row g-3 mb-3'); 
    ficha.append(crearDatoDelTicket('Solicitante', ticket.usuario + ' (' + ticket.codigo_usuario + ')'));
    ficha.append(crearDatoDelTicket('Cargo y área', ticket.cargo + ' — ' + ticket.area)); 
    ficha.append(crearDatoDelTicket('Categoría', ticket.categoria)); 
    ficha.append(crearDatoDelTicket('Prioridad', ticket.prioridad)); 
    ficha.append(crearDatoDelTicket('Técnico asignado', ticket.tecnico)); 
    ficha.append(crearDatoDelTicket('Creado', formatearFecha(ticket.creado))); 
    ficha.append(crearDatoDelTicket('Fecha límite (SLA)', formatearFecha(ticket.vence))); 
    ficha.append(crearDatoDelTicket('Cerrado', formatearFecha(ticket.cerrado))); 
    contenido.append(ficha); 

    contenido.append(crearElemento('h3', 'h6', 'Descripción')); 
    contenido.append(crearElemento('p', 'texto-con-saltos', ticket.descripcion)); 

    if (ticket.solucion) { 
        contenido.append(crearElemento('h3', 'h6', 'Solución aplicada')); 
        contenido.append(crearElemento('p', 'texto-con-saltos', ticket.solucion)); 
    }

    contenido.append(crearElemento('h3', 'h6', 'Historial')); 
    const envoltura = crearElemento('div', 'table-responsive mb-3'); 
    envoltura.append(crearTablaDeHistorial(ticket.historial)); 
    contenido.append(envoltura); 

    const acciones = crearElemento('div', 'row g-3 border-top pt-3'); 
    acciones.id = 'acciones-detalle'; 
    if (ticket.estado === 'CERRADO') {
        acciones.append(crearElemento('div', 'col-12 text-muted', 'Este ticket está CERRADO: no admite cambios de estado ni reasignación.')); 
    } else { 
        acciones.append(crearBloqueDeEstado(ticket), crearBloqueDeTecnico(ticket)); 
    }
    contenido.append(acciones); 

    document.getElementById('contenido-detalle').replaceChildren(contenido); 
    ocultarErrorDeDetalle(); 
}

async function abrirDetalle(numeroDelTicket) { 
    try { 
        const [ticket, tecnicos] = await Promise.all([obtenerTicket(numeroDelTicket), listarTecnicos()]); 
        estadoDelDetalle.ticket = ticket; 
        estadoDelDetalle.tecnicos = tecnicos.data; 
    } catch (error) { 
        mostrarAviso(describirErrorDeApi(error), 'danger'); 
        return; 
    }
    pintarDetalle(); 
    obtenerVentanaDeDetalle().show(); 
}
