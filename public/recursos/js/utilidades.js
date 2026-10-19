function crearElemento(etiqueta, clases = '', texto = '') { 
  const elemento = document.createElement(etiqueta); 
  if (clases) { 
    elemento.className = clases; 
  }
  if (texto !== '') { 
    elemento.textContent = texto; 
  }
  return elemento; 
}

function formatearFecha(textoIso) { 
  if (!textoIso) { 
    return '—'; 
  }
  return new Date(textoIso).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' }); 
}

function crearInsigniaDeEstado(estado) { 
  const clasesPorEstado = { 
    'ABIERTO': 'text-bg-primary', 
    'EN CURSO': 'text-bg-info', 
    'CERRADO': 'text-bg-secondary', 
  };
  return crearElemento('span', 'badge ' + (clasesPorEstado[estado] || 'text-bg-light'), estado); 
}

function crearInsigniaDeSla(indicador) { 
  const datosPorIndicador = { 
    'VENCIDO': { texto: 'Vencido', clases: 'text-bg-danger' }, 
    'POR_VENCER': { texto: 'Por vencer', clases: 'text-bg-warning' }, 
    'EN_TIEMPO': { texto: 'En tiempo', clases: 'text-bg-success' }, 
  };
  const datos = datosPorIndicador[indicador]; 
  if (!datos) { 
    return crearElemento('span', 'text-muted', '—'); 
  }
  return crearElemento('span', 'badge ' + datos.clases, datos.texto);
}

function mostrarAviso(mensaje, tipo = 'success') { 
  const contenedor = document.getElementById('avisos'); 
  const aviso = crearElemento('div', 'alert alert-' + tipo + ' shadow-sm mb-2', mensaje); 
  aviso.setAttribute('role', 'alert'); 
  contenedor.append(aviso); 
  setTimeout(() => aviso.remove(), 6000); 
}

function esperarAntesDeEjecutar(funcion, milisegundos) { 
  let temporizador = null; 
  return (...argumentos) => { 
    clearTimeout(temporizador); 
    temporizador = setTimeout(() => funcion(...argumentos), milisegundos); 
  };
}

function describirErrorDeApi(error) { 
  if (!(error instanceof ErrorDeApi)) { 
    return 'Ocurrió un error inesperado. Intenta de nuevo.'; 
  }
  const detalles = Object.values(error.campos || {}); 
  return detalles.length > 0 ? error.message + ': ' + detalles.join('; ') : error.message; 
}
