/*
  ARCHIVO: public/recursos/js/principal.js
  FASE: 6

  QUÉ VA AQUÍ:
    El "director de orquesta" de la página. Cuando la página termina de
    cargar:
      1. Pide los catálogos (categorías, prioridades, técnicos) y los guarda.
      2. Pinta el tablero y la primera página de la tabla.
      3. Conecta los botones (Nuevo ticket, filtros, paginación).
    También ofrece UNA función "refrescarTodo()" que vuelve a pedir tablero
    y tabla; el resto de archivos la llaman después de registrar, cambiar
    estado o reasignar.
*/

const datosCompartidos = { // Datos que varias partes de la página necesitan
  categorias: [], // Categorías (vienen de la base de datos)
  prioridades: [], // Prioridades con sus horas de SLA
  tecnicos: [], // Técnicos activos (para el filtro)
};

async function refrescarTodo() { // Vuelve a pedir tablero y tabla a la vez, sin recargar la página
  await Promise.all([pintarTablero(), pintarTabla()]); // Los dos se piden al mismo tiempo
}

async function iniciarPagina() { // Se ejecuta una vez, cuando la página terminó de cargar
  conectarEventosDeLaTabla(); // Conecta los filtros y la paginación
  conectarEventosDelFormulario(); // Conecta el botón "Nuevo ticket" y el formulario

  try { // Intenta cargar los catálogos
    const [catalogos, tecnicos] = await Promise.all([listarCatalogos(), listarTecnicos()]); // Los pide al mismo tiempo
    datosCompartidos.categorias = catalogos.categorias; // Guarda las categorías
    datosCompartidos.prioridades = catalogos.prioridades; // Guarda las prioridades
    datosCompartidos.tecnicos = tecnicos.data; // Guarda los técnicos
  } catch (error) { // Si falla (clave mal puesta, servidor apagado...)
    mostrarAviso(describirErrorDeApi(error), 'danger'); // Muestra el motivo
    return; // Sin catálogos no se puede continuar
  }

  llenarLista(document.getElementById('filtro-prioridad'), datosCompartidos.prioridades.map((prioridad) => ({ valor: prioridad.nombre, texto: prioridad.nombre })), 'Todas'); // Llena el filtro de prioridad
  llenarLista(document.getElementById('filtro-tecnico'), datosCompartidos.tecnicos.map((tecnico) => ({ valor: tecnico.id, texto: tecnico.nombre })), 'Todos'); // Llena el filtro de técnico

  await refrescarTodo(); // Dibuja el tablero y la primera página de la tabla
}

document.addEventListener('DOMContentLoaded', iniciarPagina); // Arranca cuando el HTML terminó de cargar
