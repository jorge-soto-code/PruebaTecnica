/*
  ARCHIVO: public/recursos/js/clienteApi.js
  FASE: 6

  QUÉ VA AQUÍ:
    El ÚNICO archivo que habla con la API. Una función base (llamarApi) que
    hace fetch, agrega el encabezado X-API-Key y Content-Type, y convierte la
    respuesta en datos de JavaScript. Si la API responde con error (el formato
    único { error: { code, message, fields } }), lanza un ErrorDeApi con ese
    contenido para que la pantalla pueda mostrarlo.

    Funciones que ofrece (una por endpoint):
      listarTickets(filtros, pagina, porPagina) -> GET   /api/tickets
      obtenerTicket(numero)                     -> GET   /api/tickets/{numero}
      crearTicket(datos)                        -> POST  /api/tickets
      cambiarEstado(numero, datos)              -> PATCH /api/tickets/{numero}/estado
      reasignarTecnico(numero, datos)           -> PATCH /api/tickets/{numero}/tecnico
      buscarUsuario(codigo)                     -> GET   /api/usuarios/{codigo}
      listarTecnicos()                          -> GET   /api/tecnicos
      listarCatalogos()                         -> GET   /api/categorias
      obtenerResumen()                          -> GET   /api/resumen

    Así, si algún día cambia una ruta, se corrige en un solo lugar.
*/

class ErrorDeApi extends Error { // Error propio: guarda lo que respondió la API
    constructor(estadoHttp, codigo, mensaje, campos) { // Se ejecuta al crear el error
        super(mensaje); // Guarda el mensaje en la clase base de errores
        this.estadoHttp = estadoHttp; // Número HTTP (401, 404, 409, 422...) o 0 si no hubo respuesta
        this.codigo = codigo; // Texto corto, por ejemplo TRANSICION_INVALIDA
        this.campos = campos; // Mensajes por campo (en los errores 422)
    }
}

async function llamarApi(metodo, ruta, cuerpo = null, parametros = null) { // Función base: todas las demás la usan
    if (typeof CONFIGURACION_API === 'undefined') { // Si no existe configuracion.js (se olvidó copiarlo)
        throw new ErrorDeApi(0, 'SIN_CONFIGURACION', 'Falta el archivo public/recursos/js/configuracion.js (copia configuracion.example.js)', {}); // Avisa qué hacer
    }

    let direccion = CONFIGURACION_API.urlBase + ruta; // Dirección completa, por ejemplo "api/tickets"

    if (parametros) { // Si hay filtros para la dirección (?estado=...)
        const consulta = new URLSearchParams(); // Ayudante que codifica los caracteres especiales
        for (const [nombre, valor] of Object.entries(parametros)) { // Recorre cada filtro
            if (valor !== '' && valor !== null && valor !== undefined) { // Solo agrega los que tienen valor
                consulta.set(nombre, valor); // Lo agrega a la consulta
            }
        }
        const textoDeConsulta = consulta.toString(); // Lo convierte a texto: "estado=CERRADO&page=2"
        if (textoDeConsulta !== '') { // Si quedó algún filtro
            direccion += '?' + textoDeConsulta; // Lo pega al final de la dirección
        }
    }

    const opciones = { // Configuración de la petición
        method: metodo, // GET, POST o PATCH
        headers: { 'X-API-Key': CONFIGURACION_API.claveApi, 'Accept': 'application/json' }, // La clave y el tipo de respuesta que esperamos
    };

    if (cuerpo !== null) { // Si hay datos que enviar (POST y PATCH)
        opciones.headers['Content-Type'] = 'application/json'; // Avisa que el cuerpo es JSON
        opciones.body = JSON.stringify(cuerpo); // Convierte los datos a texto JSON
    }

    let respuesta; // Aquí quedará la respuesta del servidor
    try { // Intenta hacer la petición
        respuesta = await fetch(direccion, opciones); // Envía la petición y espera la respuesta (sin recargar la página)
    } catch (errorDeRed) { // Si no hubo conexión con el servidor
        throw new ErrorDeApi(0, 'SIN_CONEXION', 'No se pudo conectar con el servidor. Revisa que Apache esté encendido.', {}); // Mensaje claro
    }

    let datos = null; // Aquí quedará el JSON de la respuesta
    try { // Intenta leer el JSON
        datos = await respuesta.json(); // Convierte el cuerpo de la respuesta en datos de JavaScript
    } catch (errorDeLectura) { // Si la respuesta no era JSON (por ejemplo, un 404 de Apache)
        datos = null; // Sigue sin datos
    }

    if (!respuesta.ok) { // Si el servidor respondió con error (401, 404, 409, 422, 500...)
        const detalle = datos && datos.error ? datos.error : {}; // Toma el bloque "error" del formato único
        throw new ErrorDeApi(respuesta.status, detalle.code || 'ERROR', detalle.message || 'La API respondió con un error (' + respuesta.status + ')', detalle.fields || {}); // Lanza el error con su contenido
    }

    return datos; // Todo bien: entrega los datos
}

function listarTickets(filtros, pagina, registrosPorPagina) { // GET /tickets con filtros y paginación
    return llamarApi('GET', '/tickets', null, { ...filtros, page: pagina, per_page: registrosPorPagina }); // Une los filtros con la página pedida
}

function obtenerTicket(numero) { // GET /tickets/{numero}
    return llamarApi('GET', '/tickets/' + encodeURIComponent(numero)); // encodeURIComponent protege caracteres especiales
}

function crearTicket(datos) { // POST /tickets
    return llamarApi('POST', '/tickets', datos); // Envía los datos del ticket nuevo
}

function cambiarEstado(numero, datos) { // PATCH /tickets/{numero}/estado
    return llamarApi('PATCH', '/tickets/' + encodeURIComponent(numero) + '/estado', datos); // Envía estado, solución y observación
}

function reasignarTecnico(numero, datos) { // PATCH /tickets/{numero}/tecnico
    return llamarApi('PATCH', '/tickets/' + encodeURIComponent(numero) + '/tecnico', datos); // Envía el id del técnico nuevo
}

function buscarUsuario(codigo) { // GET /usuarios/{codigo}
    return llamarApi('GET', '/usuarios/' + encodeURIComponent(codigo)); // Busca al solicitante por su código
}

function listarTecnicos() { // GET /tecnicos
    return llamarApi('GET', '/tecnicos'); // Técnicos activos con su carga
}

function listarCatalogos() { // GET /categorias
    return llamarApi('GET', '/categorias'); // Categorías y prioridades
}

function obtenerResumen() { // GET /resumen
    return llamarApi('GET', '/resumen'); // Números del tablero
}
