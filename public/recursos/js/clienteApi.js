class ErrorDeApi extends Error { 
    constructor(estadoHttp, codigo, mensaje, campos) { 
        super(mensaje); 
        this.estadoHttp = estadoHttp; 
        this.codigo = codigo; 
        this.campos = campos; 
    }
}

async function llamarApi(metodo, ruta, cuerpo = null, parametros = null) { 
    if (typeof CONFIGURACION_API === 'undefined') { 
        throw new ErrorDeApi(0, 'SIN_CONFIGURACION', 'Falta el archivo public/recursos/js/configuracion.js (copia configuracion.example.js)', {});
    }

    let direccion = CONFIGURACION_API.urlBase + ruta; 
    if (parametros) { 
        const consulta = new URLSearchParams(); 
        for (const [nombre, valor] of Object.entries(parametros)) { 
            if (valor !== '' && valor !== null && valor !== undefined) { 
                consulta.set(nombre, valor); 
            }
        }
        const textoDeConsulta = consulta.toString(); 
        if (textoDeConsulta !== '') { 
            direccion += '?' + textoDeConsulta;
        }
    }

    const opciones = { 
        method: metodo,
        headers: { 'X-API-Key': CONFIGURACION_API.claveApi, 'Accept': 'application/json' }, 
    };

    if (cuerpo !== null) { 
        opciones.headers['Content-Type'] = 'application/json'; 
        opciones.body = JSON.stringify(cuerpo); 
    }

    let respuesta; 
    try { 
        respuesta = await fetch(direccion, opciones); 
    } catch (errorDeRed) { 
        throw new ErrorDeApi(0, 'SIN_CONEXION', 'No se pudo conectar con el servidor. Revisa que Apache esté encendido.', {}); 
    }

    let datos = null; 
    try { 
        datos = await respuesta.json(); 
    } catch (errorDeLectura) { 
        datos = null; 
    }

    if (!respuesta.ok) { 
        const detalle = datos && datos.error ? datos.error : {}; 
        throw new ErrorDeApi(respuesta.status, detalle.code || 'ERROR', detalle.message || 'La API respondió con un error (' + respuesta.status + ')', detalle.fields || {}); 
    }
    return datos; 
}

function listarTickets(filtros, pagina, registrosPorPagina) {
    return llamarApi('GET', '/tickets', null, { ...filtros, page: pagina, per_page: registrosPorPagina }); 
}

function obtenerTicket(numero) { 
    return llamarApi('GET', '/tickets/' + encodeURIComponent(numero)); 
}

function crearTicket(datos) { 
    return llamarApi('POST', '/tickets', datos); 
}

function cambiarEstado(numero, datos) { 
    return llamarApi('PATCH', '/tickets/' + encodeURIComponent(numero) + '/estado', datos); 
}

function reasignarTecnico(numero, datos) { 
    return llamarApi('PATCH', '/tickets/' + encodeURIComponent(numero) + '/tecnico', datos); 
}

function buscarUsuario(codigo) { 
    return llamarApi('GET', '/usuarios/' + encodeURIComponent(codigo));
}

function listarTecnicos() { 
    return llamarApi('GET', '/tecnicos');
}

function listarCatalogos() { 
    return llamarApi('GET', '/categorias'); 
}

function obtenerResumen() { 
    return llamarApi('GET', '/resumen'); 
}
