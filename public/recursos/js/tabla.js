/*
  ARCHIVO: public/recursos/js/tabla.js
  FASE: 6

  QUÉ VA AQUÍ:
    Todo lo de la tabla de tickets:
      - Pedir la página actual a la API con los filtros elegidos
        (estado, prioridad, técnico, texto libre) y pintar las filas con las
        columnas: número, usuario, categoría, prioridad, técnico, estado,
        fecha de creación e indicador SLA.
      - Los botones de paginación (10 por página; la paginación la hace el
        SERVIDOR, aquí solo se pide "page=2").
      - Escuchar los filtros (con una pequeña espera al escribir para no
        llamar a la API en cada tecla).
      - Al hacer clic en una fila, abrir el detalle (detalle.js).

    SEGURIDAD (XSS): los textos que vienen de la API se insertan con
    textContent (o se escapan). Nunca con innerHTML directo.
*/

const estadoDeLaTabla = { // Lo que la tabla recuerda entre una consulta y otra
  pagina: 1, // Página que se está viendo
  registrosPorPagina: 10, // La prueba exige 10 por página
  numeroDeConsulta: 0, // Cuenta las consultas, para ignorar respuestas viejas que lleguen tarde
};

function leerFiltros() { // Lee lo que hay escrito o elegido en los filtros
  return { // Devuelve un objeto con los cuatro filtros
    estado: document.getElementById('filtro-estado').value, // ABIERTO, EN CURSO, CERRADO o vacío
    prioridad: document.getElementById('filtro-prioridad').value, // Alta, Media, Baja o vacío
    tecnico: document.getElementById('filtro-tecnico').value, // Id del técnico o vacío
    q: document.getElementById('filtro-texto').value.trim(), // Texto libre (número o nombre de usuario)
  };
}

function crearFilaDeTicket(ticket) { // Arma una fila de la tabla con un ticket
  const fila = crearElemento('tr', 'fila-clicable'); // Fila que se ve "clicable"
  fila.tabIndex = 0; // Permite llegar a la fila con el teclado (Tab)
  fila.append(crearElemento('td', 'fw-semibold', ticket.numero)); // Número del ticket
  fila.append(crearElemento('td', '', ticket.usuario)); // Nombre del solicitante
  fila.append(crearElemento('td', '', ticket.categoria)); // Categoría
  fila.append(crearElemento('td', '', ticket.prioridad)); // Prioridad
  fila.append(crearElemento('td', '', ticket.tecnico)); // Técnico asignado

  const celdaDeEstado = crearElemento('td'); // Celda para la etiqueta de estado
  celdaDeEstado.append(crearInsigniaDeEstado(ticket.estado)); // Etiqueta de color
  fila.append(celdaDeEstado); // La agrega a la fila

  fila.append(crearElemento('td', 'text-nowrap', formatearFecha(ticket.creado))); // Fecha de creación

  const celdaDeSla = crearElemento('td'); // Celda para el indicador de SLA
  celdaDeSla.append(crearInsigniaDeSla(ticket.indicador_sla)); // Etiqueta Vencido / Por vencer / En tiempo
  fila.append(celdaDeSla); // La agrega a la fila

  fila.addEventListener('click', () => abrirDetalle(ticket.numero)); // Clic en la fila: abre el detalle
  fila.addEventListener('keydown', (evento) => { // También con el teclado
    if (evento.key === 'Enter') { // Al pulsar Enter
      abrirDetalle(ticket.numero); // Abre el detalle
    }
  });
  return fila; // Devuelve la fila lista
}

function pintarFilas(tickets) { // Dibuja todas las filas de la página actual
  const cuerpoDeLaTabla = document.getElementById('cuerpo-tabla'); // El <tbody> de la tabla
  if (tickets.length === 0) { // Si ningún ticket cumple los filtros
    const filaVacia = crearElemento('tr'); // Fila para el mensaje
    const celda = crearElemento('td', 'text-center text-muted py-4', 'No hay tickets que coincidan con los filtros.'); // Mensaje
    celda.colSpan = 8; // La celda ocupa las 8 columnas
    filaVacia.append(celda); // Mete la celda en la fila
    cuerpoDeLaTabla.replaceChildren(filaVacia); // Muestra solo el mensaje
    return; // Termina
  }
  cuerpoDeLaTabla.replaceChildren(...tickets.map(crearFilaDeTicket)); // Reemplaza las filas anteriores por las nuevas
}

function crearBotonDePagina(texto, paginaDestino, deshabilitado) { // Botón Anterior o Siguiente
  const elementoDeLista = crearElemento('li', 'page-item' + (deshabilitado ? ' disabled' : '')); // Elemento de la lista de paginación
  const boton = crearElemento('button', 'page-link', texto); // El botón en sí
  boton.type = 'button'; // Evita que se comporte como botón de envío de formulario
  boton.addEventListener('click', () => { // Al hacer clic
    estadoDeLaTabla.pagina = paginaDestino; // Cambia la página pedida
    pintarTabla(); // Vuelve a pedir la tabla (solo la tabla, no el tablero)
  });
  elementoDeLista.append(boton); // Mete el botón en el elemento
  return elementoDeLista; // Devuelve el elemento listo
}

function pintarPaginacion(meta) { // Dibuja los botones y el texto de paginación
  const totalDePaginas = Math.max(1, Math.ceil(meta.total / meta.per_page)); // Cuántas páginas hay en total
  const desde = meta.total === 0 ? 0 : (meta.page - 1) * meta.per_page + 1; // Primer ticket mostrado
  const hasta = Math.min(meta.page * meta.per_page, meta.total); // Último ticket mostrado

  const contenedor = crearElemento('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2'); // Texto a la izquierda, botones a la derecha
  contenedor.append(crearElemento('div', 'text-muted small', 'Mostrando ' + desde + '–' + hasta + ' de ' + meta.total + ' tickets')); // Resumen

  const lista = crearElemento('ul', 'pagination pagination-sm mb-0'); // Lista de botones de Bootstrap
  lista.append(crearBotonDePagina('Anterior', meta.page - 1, meta.page <= 1)); // Anterior (apagado en la primera página)
  lista.append(crearElemento('li', 'page-item disabled', '')); // Espacio para el texto de la página
  lista.lastChild.append(crearElemento('span', 'page-link', 'Página ' + meta.page + ' de ' + totalDePaginas)); // Ejemplo: "Página 2 de 3"
  lista.append(crearBotonDePagina('Siguiente', meta.page + 1, meta.page >= totalDePaginas)); // Siguiente (apagado en la última página)
  contenedor.append(lista); // Mete la lista en el contenedor

  document.getElementById('paginacion').replaceChildren(contenedor); // Reemplaza la paginación anterior
}

async function pintarTabla() { // Pide la página actual a la API y dibuja tabla y paginación
  estadoDeLaTabla.numeroDeConsulta += 1; // Esta es una consulta nueva
  const miConsulta = estadoDeLaTabla.numeroDeConsulta; // Recuerda cuál es la mía

  let respuesta; // Aquí quedará la respuesta
  try { // Intenta pedir los tickets
    respuesta = await listarTickets(leerFiltros(), estadoDeLaTabla.pagina, estadoDeLaTabla.registrosPorPagina); // GET /tickets
  } catch (error) { // Si la API falla
    if (miConsulta === estadoDeLaTabla.numeroDeConsulta) { // Solo avisa si no hay una consulta más nueva
      mostrarAviso(describirErrorDeApi(error), 'danger'); // Muestra el motivo
    }
    return; // No dibuja nada
  }

  if (miConsulta !== estadoDeLaTabla.numeroDeConsulta) { // Si mientras esperaba se hizo otra consulta
    return; // Ignora esta respuesta vieja
  }

  pintarFilas(respuesta.data); // Dibuja las filas
  pintarPaginacion(respuesta.meta); // Dibuja la paginación
}

function volverALaPrimeraPaginaYPintar() { // Se usa cuando cambia un filtro
  estadoDeLaTabla.pagina = 1; // Un filtro nuevo empieza en la página 1
  pintarTabla(); // Vuelve a pedir la tabla
}

function conectarEventosDeLaTabla() { // Conecta los filtros con la tabla
  for (const idDelFiltro of ['filtro-estado', 'filtro-prioridad', 'filtro-tecnico']) { // Los tres filtros de lista desplegable
    document.getElementById(idDelFiltro).addEventListener('change', volverALaPrimeraPaginaYPintar); // Al cambiar la opción, consulta
  }
  document.getElementById('filtro-texto').addEventListener('input', esperarAntesDeEjecutar(volverALaPrimeraPaginaYPintar, 400)); // Al escribir, espera 0,4 s sin teclear y consulta
  document.getElementById('boton-limpiar-filtros').addEventListener('click', () => { // Botón "Limpiar"
    document.getElementById('formulario-filtros').reset(); // Deja los filtros vacíos
    volverALaPrimeraPaginaYPintar(); // Consulta sin filtros
  });
  document.getElementById('formulario-filtros').addEventListener('submit', (evento) => { // Si se pulsa Enter dentro del filtro de texto
    evento.preventDefault(); // Evita que el formulario recargue la página
    volverALaPrimeraPaginaYPintar(); // Consulta con los filtros actuales
  });
}
