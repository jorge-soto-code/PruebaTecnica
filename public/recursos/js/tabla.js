const estadoDeLaTabla = {
  pagina: 1, 
  registrosPorPagina: 10, 
  numeroDeConsulta: 0, 
};

function leerFiltros() { 
  return { 
    estado: document.getElementById('filtro-estado').value, 
    prioridad: document.getElementById('filtro-prioridad').value, 
    tecnico: document.getElementById('filtro-tecnico').value, 
    q: document.getElementById('filtro-texto').value.trim(), 
  };
}

function crearFilaDeTicket(ticket) {
  const fila = crearElemento('tr', 'fila-clicable'); 
  fila.tabIndex = 0; 
  fila.append(crearElemento('td', 'fw-semibold', ticket.numero)); 
  fila.append(crearElemento('td', '', ticket.usuario)); 
  fila.append(crearElemento('td', '', ticket.categoria)); 
  fila.append(crearElemento('td', '', ticket.prioridad)); 
  fila.append(crearElemento('td', '', ticket.tecnico)); 

  const celdaDeEstado = crearElemento('td'); 
  celdaDeEstado.append(crearInsigniaDeEstado(ticket.estado)); 
  fila.append(celdaDeEstado); 

  fila.append(crearElemento('td', 'text-nowrap', formatearFecha(ticket.creado))); 

  const celdaDeSla = crearElemento('td'); 
  celdaDeSla.append(crearInsigniaDeSla(ticket.indicador_sla)); 
  fila.append(celdaDeSla); 

  fila.addEventListener('click', () => abrirDetalle(ticket.numero)); 
  fila.addEventListener('keydown', (evento) => { 
    if (evento.key === 'Enter') { 
      abrirDetalle(ticket.numero); 
    }
  });
  return fila; 
}

function pintarFilas(tickets) { 
  const cuerpoDeLaTabla = document.getElementById('cuerpo-tabla'); 
  if (tickets.length === 0) { 
    const filaVacia = crearElemento('tr'); 
    const celda = crearElemento('td', 'text-center text-muted py-4', 'No hay tickets que coincidan con los filtros.'); 
    celda.colSpan = 8; 
    filaVacia.append(celda);
    cuerpoDeLaTabla.replaceChildren(filaVacia); 
    return; 
  }
  cuerpoDeLaTabla.replaceChildren(...tickets.map(crearFilaDeTicket)); 
}

function crearBotonDePagina(texto, paginaDestino, deshabilitado) { 
  const elementoDeLista = crearElemento('li', 'page-item' + (deshabilitado ? ' disabled' : '')); 
  const boton = crearElemento('button', 'page-link', texto); 
  boton.type = 'button'; 
  boton.addEventListener('click', () => { 
    estadoDeLaTabla.pagina = paginaDestino; 
    pintarTabla(); 
  });
  elementoDeLista.append(boton); 
  return elementoDeLista; 
}

function pintarPaginacion(meta) { 
  const totalDePaginas = Math.max(1, Math.ceil(meta.total / meta.per_page)); 
  const desde = meta.total === 0 ? 0 : (meta.page - 1) * meta.per_page + 1; 
  const hasta = Math.min(meta.page * meta.per_page, meta.total); 

  const contenedor = crearElemento('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2');
  contenedor.append(crearElemento('div', 'text-muted small', 'Mostrando ' + desde + '–' + hasta + ' de ' + meta.total + ' tickets')); 

  const lista = crearElemento('ul', 'pagination pagination-sm mb-0'); 
  lista.append(crearBotonDePagina('Anterior', meta.page - 1, meta.page <= 1)); 
  lista.append(crearElemento('li', 'page-item disabled', ''));
  lista.lastChild.append(crearElemento('span', 'page-link', 'Página ' + meta.page + ' de ' + totalDePaginas)); 
  lista.append(crearBotonDePagina('Siguiente', meta.page + 1, meta.page >= totalDePaginas)); 
  contenedor.append(lista); 

  document.getElementById('paginacion').replaceChildren(contenedor); 
}

async function pintarTabla() { 
  estadoDeLaTabla.numeroDeConsulta += 1; 
  const miConsulta = estadoDeLaTabla.numeroDeConsulta; 

  let respuesta; 
  try { 
    respuesta = await listarTickets(leerFiltros(), estadoDeLaTabla.pagina, estadoDeLaTabla.registrosPorPagina); 
  } catch (error) { 
    if (miConsulta === estadoDeLaTabla.numeroDeConsulta) { 
      mostrarAviso(describirErrorDeApi(error), 'danger'); 
    }
    return;
  }

  if (miConsulta !== estadoDeLaTabla.numeroDeConsulta) { 
    return; 
  }

  pintarFilas(respuesta.data); 
  pintarPaginacion(respuesta.meta); 
}

function volverALaPrimeraPaginaYPintar() { 
  estadoDeLaTabla.pagina = 1; 
  pintarTabla(); 
}

function conectarEventosDeLaTabla() { 
  for (const idDelFiltro of ['filtro-estado', 'filtro-prioridad', 'filtro-tecnico']) { 
    document.getElementById(idDelFiltro).addEventListener('change', volverALaPrimeraPaginaYPintar); 
  }
  document.getElementById('filtro-texto').addEventListener('input', esperarAntesDeEjecutar(volverALaPrimeraPaginaYPintar, 400)); 
  document.getElementById('boton-limpiar-filtros').addEventListener('click', () => { 
    document.getElementById('formulario-filtros').reset(); 
    volverALaPrimeraPaginaYPintar(); 
  });
  document.getElementById('formulario-filtros').addEventListener('submit', (evento) => { 
    evento.preventDefault(); 
    volverALaPrimeraPaginaYPintar(); 
  });
}
