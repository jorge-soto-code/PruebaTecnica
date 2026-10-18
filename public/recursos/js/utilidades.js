/*
  ARCHIVO: public/recursos/js/utilidades.js
  FASE: 6

  QUÉ VA AQUÍ:
    Funciones pequeñas que usan todos los demás archivos:
      - crearElemento(): crea etiquetas HTML con texto SEGURO (textContent).
      - formatearFecha(): convierte una fecha ISO a formato legible en español.
      - crearInsigniaDeEstado() y crearInsigniaDeSla(): las etiquetas de colores.
      - mostrarAviso(): mensajes emergentes (éxito o error).
      - esperarAntesDeEjecutar(): evita llamar a la API en cada tecla.
      - describirErrorDeApi(): convierte un error de la API en un texto.

  SEGURIDAD (XSS): todo texto que viene de la API se pone con textContent.
  Nunca se usa innerHTML con datos externos.
*/

function crearElemento(etiqueta, clases = '', texto = '') { // Crea un elemento HTML de forma segura
  const elemento = document.createElement(etiqueta); // Crea la etiqueta pedida (div, td, span...)
  if (clases) { // Si se indicaron clases de Bootstrap
    elemento.className = clases; // Las asigna al elemento
  }
  if (texto !== '') { // Si se indicó un texto
    elemento.textContent = texto; // Lo pone como TEXTO, no como HTML: así no se puede inyectar código (XSS)
  }
  return elemento; // Devuelve el elemento listo para insertar
}

function formatearFecha(textoIso) { // Convierte "2026-10-16T09:30:00-05:00" en "16/10/2026, 9:30 a. m."
  if (!textoIso) { // Si no hay fecha (por ejemplo, un ticket sin cerrar)
    return '—'; // Muestra un guion
  }
  return new Date(textoIso).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' }); // Fecha corta y hora corta en español
}

function crearInsigniaDeEstado(estado) { // Etiqueta de color para el estado del ticket
  const clasesPorEstado = { // Color de Bootstrap según el estado
    'ABIERTO': 'text-bg-primary', // Azul
    'EN CURSO': 'text-bg-info', // Celeste
    'CERRADO': 'text-bg-secondary', // Gris
  };
  return crearElemento('span', 'badge ' + (clasesPorEstado[estado] || 'text-bg-light'), estado); // Etiqueta con el texto del estado
}

function crearInsigniaDeSla(indicador) { // Etiqueta de color para el indicador de SLA
  const datosPorIndicador = { // Texto y color según lo que responde la API
    'VENCIDO': { texto: 'Vencido', clases: 'text-bg-danger' }, // Rojo
    'POR_VENCER': { texto: 'Por vencer', clases: 'text-bg-warning' }, // Amarillo
    'EN_TIEMPO': { texto: 'En tiempo', clases: 'text-bg-success' }, // Verde
  };
  const datos = datosPorIndicador[indicador]; // Busca los datos del indicador recibido
  if (!datos) { // Si es null (ticket cerrado: el SLA no aplica)
    return crearElemento('span', 'text-muted', '—'); // Muestra un guion
  }
  return crearElemento('span', 'badge ' + datos.clases, datos.texto); // Etiqueta con color
}

function mostrarAviso(mensaje, tipo = 'success') { // Mensaje emergente arriba a la derecha (tipo: success, danger, warning)
  const contenedor = document.getElementById('avisos'); // Zona de la página reservada para los avisos
  const aviso = crearElemento('div', 'alert alert-' + tipo + ' shadow-sm mb-2', mensaje); // Alerta de Bootstrap con el mensaje como texto
  aviso.setAttribute('role', 'alert'); // Ayuda a los lectores de pantalla a anunciarlo
  contenedor.append(aviso); // Lo agrega a la zona de avisos
  setTimeout(() => aviso.remove(), 6000); // A los 6 segundos lo quita solo
}

function esperarAntesDeEjecutar(funcion, milisegundos) { // Retrasa una función hasta que la persona deje de escribir
  let temporizador = null; // Guarda la espera pendiente
  return (...argumentos) => { // Devuelve la versión "con espera" de la función
    clearTimeout(temporizador); // Cancela la espera anterior (siguió escribiendo)
    temporizador = setTimeout(() => funcion(...argumentos), milisegundos); // Programa la ejecución para más tarde
  };
}

function describirErrorDeApi(error) { // Convierte un error en un texto para mostrar
  if (!(error instanceof ErrorDeApi)) { // Si no es un error de la API (algo inesperado del navegador)
    return 'Ocurrió un error inesperado. Intenta de nuevo.'; // Mensaje genérico
  }
  const detalles = Object.values(error.campos || {}); // Mensajes por campo (si la API los envió)
  return detalles.length > 0 ? error.message + ': ' + detalles.join('; ') : error.message; // Mensaje general + detalles
}
