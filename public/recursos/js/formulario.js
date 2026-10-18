/*
  ARCHIVO: public/recursos/js/formulario.js
  FASE: 6

  QUÉ VA AQUÍ:
    El formulario para registrar un ticket:
      - Al escribir el código del usuario, busca por AJAX y muestra nombre,
        cargo y área. Si el código no existe o el usuario está inactivo,
        muestra el aviso y bloquea el botón "Registrar".
      - Llena la lista de categorías con lo que entrega la API (no escritas
        a mano en el HTML).
      - Muestra los técnicos activos con sus tickets abiertos/en curso y deja
        seleccionado por defecto el de menor carga. Un técnico con 5 tickets
        aparece deshabilitado.
      - Contador de caracteres de la descripción (mínimo 20, máximo 1.000).
      - Al enviar, llama a la API; si responde 422 muestra los errores junto
        a cada campo; si responde 201, cierra el formulario y refresca
        tablero y tabla sin recargar la página.
*/

const estadoDelFormulario = { // Lo que el formulario recuerda mientras está abierto
    usuarioValido: false, // true solo si el código existe y el usuario está activo
    numeroDeBusqueda: 0, // Cuenta las búsquedas de usuario, para ignorar respuestas viejas
    ventana: null, // La ventana emergente (modal) de Bootstrap
};

function obtenerVentanaNueva() { // Devuelve la ventana emergente, creándola la primera vez
    if (estadoDelFormulario.ventana === null) { // Si todavía no existe
        estadoDelFormulario.ventana = new bootstrap.Modal(document.getElementById('modal-nuevo')); // La crea con Bootstrap
    }
    return estadoDelFormulario.ventana; // La devuelve
}

function llenarLista(lista, opciones, textoDeEspera) { // Llena un <select> con opciones [{valor, texto, deshabilitada}]
    const elementos = []; // Aquí se arman las opciones
    if (textoDeEspera) { // Si hay una primera opción vacía ("Selecciona...")
        elementos.push(new Option(textoDeEspera, '')); // La agrega con valor vacío
    }
    for (const opcion of opciones) { // Recorre cada opción pedida
        const elemento = new Option(opcion.texto, opcion.valor); // Crea la opción (el texto se inserta como texto, no como HTML)
        elemento.disabled = Boolean(opcion.deshabilitada); // Deshabilita las que no se pueden elegir
        elementos.push(elemento); // La agrega a la lista de opciones
    }
    lista.replaceChildren(...elementos); // Reemplaza las opciones anteriores
}

function llenarListaDeTecnicos(lista, tecnicos, tecnicoActual) { // Llena un <select> de técnicos con su carga y sugiere el de menor carga
    const opciones = tecnicos.map((tecnico) => { // Convierte cada técnico en una opción
        const esElActual = tecnicoActual !== undefined && tecnico.nombre === tecnicoActual; // En el detalle: el técnico que ya tiene el ticket
        let texto = tecnico.nombre + ' (' + tecnico.carga_actual + '/' + tecnico.tope_de_carga + ' activos)'; // Ejemplo: "Jhon (4/5 activos)"
        if (esElActual) { // Si ya es el técnico del ticket
            texto += ' — actual'; // Lo indica
        } else if (!tecnico.puede_recibir_mas) { // Si está en el tope
            texto += ' — sin cupo'; // Lo indica
        }
        return { valor: tecnico.id, texto: texto, deshabilitada: !tecnico.puede_recibir_mas || esElActual }; // Deshabilitado si no puede recibir o ya es el actual
    });
    llenarLista(lista, opciones, ''); // Llena el <select> sin opción vacía
    const primeraDisponible = opciones.find((opcion) => !opcion.deshabilitada); // La lista viene de menor a mayor carga: la primera libre es la sugerida
    if (primeraDisponible) { // Si hay alguna disponible
        lista.value = String(primeraDisponible.valor); // La deja seleccionada por defecto
    }
}

function limpiarErroresDelFormulario() { // Quita los mensajes de error anteriores
    for (const campo of document.querySelectorAll('#formulario-ticket .is-invalid')) { // Todos los campos marcados en rojo
        campo.classList.remove('is-invalid'); // Les quita el rojo
    }
    for (const mensaje of document.querySelectorAll('#formulario-ticket [data-error-de]')) { // Todos los textos de error
        mensaje.textContent = ''; // Los vacía
    }
    const errorGeneral = document.getElementById('error-general-nuevo'); // Zona de errores que no son de un campo
    errorGeneral.textContent = ''; // La vacía
    errorGeneral.classList.add('d-none'); // La oculta
}

function mostrarErroresDeCampos(campos) { // Muestra los errores 422 junto a cada campo
    for (const [nombreDelCampo, mensaje] of Object.entries(campos)) { // Recorre cada campo con error
        const entrada = document.querySelector('#formulario-ticket [name="' + nombreDelCampo + '"]'); // Busca el campo por su nombre
        const texto = document.querySelector('#formulario-ticket [data-error-de="' + nombreDelCampo + '"]'); // Busca dónde escribir el error
        if (entrada && texto) { // Si ambos existen
            entrada.classList.add('is-invalid'); // Marca el campo en rojo
            texto.textContent = mensaje; // Escribe el mensaje como texto
        }
    }
}

function mostrarErrorGeneralDelFormulario(mensaje) { // Muestra un error que no pertenece a un solo campo
    const errorGeneral = document.getElementById('error-general-nuevo'); // Zona de errores generales
    errorGeneral.textContent = mensaje; // Escribe el mensaje como texto
    errorGeneral.classList.remove('d-none'); // La hace visible
}

function actualizarBotonRegistrar() { // Activa o desactiva el botón "Registrar"
    document.getElementById('boton-registrar').disabled = !estadoDelFormulario.usuarioValido; // Solo se activa con un usuario válido
}

function actualizarContadorDeDescripcion() { // Muestra cuántos caracteres lleva la descripción
    const largo = document.getElementById('descripcion-ticket').value.length; // Caracteres escritos
    const contador = document.getElementById('contador-descripcion'); // Texto del contador
    contador.textContent = largo + ' / 1000 (mínimo 20)'; // Ejemplo: "35 / 1000 (mínimo 20)"
    contador.className = (largo < 20 || largo > 1000) ? 'form-text text-danger' : 'form-text'; // Rojo si está fuera del rango
}

async function buscarYMostrarUsuario() { // Busca al usuario por el código escrito y muestra sus datos
    estadoDelFormulario.numeroDeBusqueda += 1; // Esta es una búsqueda nueva
    const miBusqueda = estadoDelFormulario.numeroDeBusqueda; // Recuerda cuál es la mía
    const codigo = document.getElementById('codigo-usuario').value.trim().toUpperCase(); // Código escrito, en mayúsculas
    const zonaDeUsuario = document.getElementById('datos-usuario'); // Donde se muestran los datos del usuario

    estadoDelFormulario.usuarioValido = false; // Hasta comprobar, no es válido
    actualizarBotonRegistrar(); // Apaga el botón
    zonaDeUsuario.replaceChildren(); // Borra lo que se mostraba

    if (codigo === '') { // Si el campo quedó vacío
        return; // No hay nada que buscar
    }

    let usuario; // Aquí quedarán los datos del usuario
    try { // Intenta buscar
        usuario = await buscarUsuario(codigo); // GET /usuarios/{codigo}
    } catch (error) { // Si no existe (404) o falla algo
        if (miBusqueda !== estadoDelFormulario.numeroDeBusqueda) { // Si ya hay una búsqueda más nueva
            return; // Ignora esta
        }
        const mensaje = error instanceof ErrorDeApi && error.estadoHttp === 404 ? 'No existe un usuario con ese código.' : describirErrorDeApi(error); // Mensaje según el error
        zonaDeUsuario.replaceChildren(crearElemento('div', 'alert alert-danger py-2 mb-0', mensaje)); // Lo muestra en rojo
        return; // No se puede registrar
    }

    if (miBusqueda !== estadoDelFormulario.numeroDeBusqueda) { // Si ya hay una búsqueda más nueva
        return; // Ignora esta respuesta vieja
    }

    if (!usuario.activo) { // Si el usuario existe pero está inactivo
        zonaDeUsuario.replaceChildren(crearElemento('div', 'alert alert-warning py-2 mb-0', usuario.nombre + ' está INACTIVO: no se pueden registrar tickets a su nombre.')); // Aviso amarillo
        return; // No se puede registrar
    }

    const tarjeta = crearElemento('div', 'alert alert-success py-2 mb-0'); // Cuadro verde con los datos
    tarjeta.append(crearElemento('div', 'fw-semibold', usuario.nombre)); // Nombre
    tarjeta.append(crearElemento('div', 'small', usuario.cargo + ' — ' + usuario.area)); // Cargo y área
    zonaDeUsuario.replaceChildren(tarjeta); // Lo muestra
    estadoDelFormulario.usuarioValido = true; // Ahora sí es válido
    actualizarBotonRegistrar(); // Enciende el botón
}

async function abrirFormularioNuevo() { // Abre la ventana para registrar un ticket
    document.getElementById('formulario-ticket').reset(); // Deja el formulario vacío
    limpiarErroresDelFormulario(); // Quita errores anteriores
    document.getElementById('datos-usuario').replaceChildren(); // Quita los datos del usuario anterior
    estadoDelFormulario.usuarioValido = false; // Empieza sin usuario válido
    actualizarBotonRegistrar(); // Apaga el botón
    actualizarContadorDeDescripcion(); // Deja el contador en 0

    llenarLista(document.getElementById('categoria-ticket'), datosCompartidos.categorias.map((categoria) => ({ valor: categoria.id, texto: categoria.nombre })), 'Selecciona una categoría...'); // Categorías que vienen de la base de datos
    llenarLista(document.getElementById('prioridad-ticket'), datosCompartidos.prioridades.map((prioridad) => ({ valor: prioridad.nombre, texto: prioridad.nombre + ' (' + prioridad.horas_sla + ' h)' })), 'Selecciona una prioridad...'); // Prioridades con sus horas de SLA

    obtenerVentanaNueva().show(); // Muestra la ventana

    try { // Pide los técnicos con su carga actual (siempre frescos)
        const respuesta = await listarTecnicos(); // GET /tecnicos
        llenarListaDeTecnicos(document.getElementById('tecnico-ticket'), respuesta.data); // Llena la lista y sugiere el de menor carga
    } catch (error) { // Si falla
        mostrarErrorGeneralDelFormulario(describirErrorDeApi(error)); // Muestra el motivo
    }
}

async function enviarFormularioNuevo(evento) { // Se ejecuta al pulsar "Registrar"
    evento.preventDefault(); // Evita que el navegador recargue la página
    limpiarErroresDelFormulario(); // Quita errores anteriores

    const datos = { // Datos que se envían a la API
        codigo_usuario: document.getElementById('codigo-usuario').value.trim().toUpperCase(), // Código del solicitante
        categoria_id: Number(document.getElementById('categoria-ticket').value) || null, // Categoría (null si no eligió)
        descripcion: document.getElementById('descripcion-ticket').value, // Descripción
        prioridad: document.getElementById('prioridad-ticket').value, // Prioridad
        tecnico_id: Number(document.getElementById('tecnico-ticket').value) || null, // Técnico (null si no eligió)
    };

    const boton = document.getElementById('boton-registrar'); // El botón de enviar
    boton.disabled = true; // Lo apaga mientras se envía (evita doble clic)
    try { // Intenta crear el ticket
        const ticket = await crearTicket(datos); // POST /tickets
        obtenerVentanaNueva().hide(); // Cierra la ventana
        mostrarAviso('Ticket ' + ticket.numero + ' registrado y asignado a ' + ticket.tecnico + '.', 'success'); // Confirma
        await refrescarTodo(); // Actualiza tablero y tabla sin recargar la página
    } catch (error) { // Si la API rechazó el ticket
        if (error instanceof ErrorDeApi && Object.keys(error.campos).length > 0) { // Si trae errores por campo (422)
            mostrarErroresDeCampos(error.campos); // Los muestra junto a cada campo
            if (error.campos.codigo_usuario) { // Si el problema es el usuario
                mostrarErrorGeneralDelFormulario(error.message); // También lo explica arriba
            }
        } else { // Si es otro error (409: el técnico llegó al tope, o fallo de red)
            mostrarErrorGeneralDelFormulario(describirErrorDeApi(error)); // Muestra el mensaje de la API
            try { // Vuelve a pedir los técnicos por si cambió la carga
                llenarListaDeTecnicos(document.getElementById('tecnico-ticket'), (await listarTecnicos()).data); // Actualiza la lista
            } catch (errorAlRecargar) { // Si tampoco se pudo
                // No hace nada más: el error principal ya se mostró
            }
        }
    } finally { // Pase lo que pase
        actualizarBotonRegistrar(); // Deja el botón según el estado del usuario
    }
}

function conectarEventosDelFormulario() { // Conecta el formulario con sus acciones
    document.getElementById('boton-nuevo').addEventListener('click', abrirFormularioNuevo); // Botón "Nuevo ticket"
    document.getElementById('codigo-usuario').addEventListener('input', esperarAntesDeEjecutar(buscarYMostrarUsuario, 400)); // Al escribir el código, busca (con espera)
    document.getElementById('codigo-usuario').addEventListener('input', () => { // Mientras se escribe, apaga el botón de inmediato
        estadoDelFormulario.usuarioValido = false; // El usuario aún no está comprobado
        actualizarBotonRegistrar(); // Apaga el botón
    });
    document.getElementById('descripcion-ticket').addEventListener('input', actualizarContadorDeDescripcion); // Actualiza el contador al escribir
    document.getElementById('formulario-ticket').addEventListener('submit', enviarFormularioNuevo); // Al enviar el formulario
}
