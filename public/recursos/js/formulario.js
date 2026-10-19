const estadoDelFormulario = { 
    usuarioValido: false, 
    numeroDeBusqueda: 0, 
    ventana: null, 
};

function obtenerVentanaNueva() { 
    if (estadoDelFormulario.ventana === null) {
        estadoDelFormulario.ventana = new bootstrap.Modal(document.getElementById('modal-nuevo')); 
    }
    return estadoDelFormulario.ventana; 
}

function llenarLista(lista, opciones, textoDeEspera) { 
    const elementos = []; 
    if (textoDeEspera) { 
        elementos.push(new Option(textoDeEspera, '')); 
    }
    for (const opcion of opciones) { 
        const elemento = new Option(opcion.texto, opcion.valor); 
        elemento.disabled = Boolean(opcion.deshabilitada); 
        elementos.push(elemento); 
    }
    lista.replaceChildren(...elementos); 
}

function llenarListaDeTecnicos(lista, tecnicos, tecnicoActual) { 
    const opciones = tecnicos.map((tecnico) => { 
        const esElActual = tecnicoActual !== undefined && tecnico.nombre === tecnicoActual; 
        let texto = tecnico.nombre + ' (' + tecnico.carga_actual + '/' + tecnico.tope_de_carga + ' activos)'; 
        if (esElActual) { 
            texto += ' — actual'; 
        } else if (!tecnico.puede_recibir_mas) { 
            texto += ' — sin cupo'; 
        }
        return { valor: tecnico.id, texto: texto, deshabilitada: !tecnico.puede_recibir_mas || esElActual }; 
    });
    llenarLista(lista, opciones, ''); 
    const primeraDisponible = opciones.find((opcion) => !opcion.deshabilitada); 
    if (primeraDisponible) { 
        lista.value = String(primeraDisponible.valor); 
    }
}

function limpiarErroresDelFormulario() { 
    for (const campo of document.querySelectorAll('#formulario-ticket .is-invalid')) { // Todos los campos marcados en rojo
        campo.classList.remove('is-invalid'); 
    }
    for (const mensaje of document.querySelectorAll('#formulario-ticket [data-error-de]')) { // Todos los textos de error
        mensaje.textContent = ''; 
    }
    const errorGeneral = document.getElementById('error-general-nuevo'); 
    errorGeneral.textContent = ''; 
    errorGeneral.classList.add('d-none'); 
}

function mostrarErroresDeCampos(campos) { 
    for (const [nombreDelCampo, mensaje] of Object.entries(campos)) { 
        const entrada = document.querySelector('#formulario-ticket [name="' + nombreDelCampo + '"]'); 
        const texto = document.querySelector('#formulario-ticket [data-error-de="' + nombreDelCampo + '"]'); 
        if (entrada && texto) { 
            entrada.classList.add('is-invalid'); 
            texto.textContent = mensaje; 
        }
    }
}

function mostrarErrorGeneralDelFormulario(mensaje) { 
    const errorGeneral = document.getElementById('error-general-nuevo'); 
    errorGeneral.textContent = mensaje;
    errorGeneral.classList.remove('d-none'); 
}

function actualizarBotonRegistrar() { 
    document.getElementById('boton-registrar').disabled = !estadoDelFormulario.usuarioValido; 
}

function actualizarContadorDeDescripcion() { 
    const largo = document.getElementById('descripcion-ticket').value.length; 
    const contador = document.getElementById('contador-descripcion'); 
    contador.textContent = largo + ' / 1000 (mínimo 20)'; 
    contador.className = (largo < 20 || largo > 1000) ? 'form-text text-danger' : 'form-text'; 
}

async function buscarYMostrarUsuario() { 
    estadoDelFormulario.numeroDeBusqueda += 1; 
    const miBusqueda = estadoDelFormulario.numeroDeBusqueda; 
    const codigo = document.getElementById('codigo-usuario').value.trim().toUpperCase(); 
    const zonaDeUsuario = document.getElementById('datos-usuario'); 

    estadoDelFormulario.usuarioValido = false; 
    actualizarBotonRegistrar(); 
    zonaDeUsuario.replaceChildren(); 

    if (codigo === '') { 
        return; 
    }

    let usuario; 
    try { 
        usuario = await buscarUsuario(codigo); 
    } catch (error) { 
        if (miBusqueda !== estadoDelFormulario.numeroDeBusqueda) { 
            return; 
        }
        const mensaje = error instanceof ErrorDeApi && error.estadoHttp === 404 ? 'No existe un usuario con ese código.' : describirErrorDeApi(error); 
        zonaDeUsuario.replaceChildren(crearElemento('div', 'alert alert-danger py-2 mb-0', mensaje)); // Lo muestra en rojo
        return; 
    }

    if (miBusqueda !== estadoDelFormulario.numeroDeBusqueda) { 
        return; 
    }

    if (!usuario.activo) { 
        zonaDeUsuario.replaceChildren(crearElemento('div', 'alert alert-warning py-2 mb-0', usuario.nombre + ' está INACTIVO: no se pueden registrar tickets a su nombre.')); 
        return; 
    }

    const tarjeta = crearElemento('div', 'alert alert-success py-2 mb-0'); 
    tarjeta.append(crearElemento('div', 'fw-semibold', usuario.nombre)); 
    tarjeta.append(crearElemento('div', 'small', usuario.cargo + ' — ' + usuario.area)); 
    zonaDeUsuario.replaceChildren(tarjeta); 
    estadoDelFormulario.usuarioValido = true;
    actualizarBotonRegistrar(); 
}

async function abrirFormularioNuevo() { 
    document.getElementById('formulario-ticket').reset();
    limpiarErroresDelFormulario(); 
    document.getElementById('datos-usuario').replaceChildren(); 
    estadoDelFormulario.usuarioValido = false; 
    actualizarBotonRegistrar(); 
    actualizarContadorDeDescripcion();

    llenarLista(document.getElementById('categoria-ticket'), datosCompartidos.categorias.map((categoria) => ({ valor: categoria.id, texto: categoria.nombre })), 'Selecciona una categoría...');
    llenarLista(document.getElementById('prioridad-ticket'), datosCompartidos.prioridades.map((prioridad) => ({ valor: prioridad.nombre, texto: prioridad.nombre + ' (' + prioridad.horas_sla + ' h)' })), 'Selecciona una prioridad...'); 

    obtenerVentanaNueva().show(); 

    try { 
        const respuesta = await listarTecnicos(); 
        llenarListaDeTecnicos(document.getElementById('tecnico-ticket'), respuesta.data); 
    } catch (error) { 
        mostrarErrorGeneralDelFormulario(describirErrorDeApi(error)); 
    }
}

async function enviarFormularioNuevo(evento) { 
    evento.preventDefault(); 
    limpiarErroresDelFormulario(); 

    const datos = { 
        codigo_usuario: document.getElementById('codigo-usuario').value.trim().toUpperCase(), 
        categoria_id: Number(document.getElementById('categoria-ticket').value) || null, 
        descripcion: document.getElementById('descripcion-ticket').value, 
        prioridad: document.getElementById('prioridad-ticket').value, 
        tecnico_id: Number(document.getElementById('tecnico-ticket').value) || null,
    };

    const boton = document.getElementById('boton-registrar'); 
    boton.disabled = true; 
    try { 
        const ticket = await crearTicket(datos); 
        obtenerVentanaNueva().hide();
        mostrarAviso('Ticket ' + ticket.numero + ' registrado y asignado a ' + ticket.tecnico + '.', 'success'); 
        await refrescarTodo(); 
    } catch (error) { 
        if (error instanceof ErrorDeApi && Object.keys(error.campos).length > 0) { 
            mostrarErroresDeCampos(error.campos); 
            if (error.campos.codigo_usuario) { 
                mostrarErrorGeneralDelFormulario(error.message); 
            }
        } else { 
            mostrarErrorGeneralDelFormulario(describirErrorDeApi(error)); 
            try { 
                llenarListaDeTecnicos(document.getElementById('tecnico-ticket'), (await listarTecnicos()).data); 
            } catch (errorAlRecargar) { 
                // No hace nada más: el error principal ya se mostró
            }
        }
    } finally { 
        actualizarBotonRegistrar(); 
    }
}

function conectarEventosDelFormulario() { 
    document.getElementById('boton-nuevo').addEventListener('click', abrirFormularioNuevo); 
    document.getElementById('codigo-usuario').addEventListener('input', esperarAntesDeEjecutar(buscarYMostrarUsuario, 400)); 
    document.getElementById('codigo-usuario').addEventListener('input', () => {
        estadoDelFormulario.usuarioValido = false; 
        actualizarBotonRegistrar(); 
    });
    document.getElementById('descripcion-ticket').addEventListener('input', actualizarContadorDeDescripcion); 
    document.getElementById('formulario-ticket').addEventListener('submit', enviarFormularioNuevo); 
}
