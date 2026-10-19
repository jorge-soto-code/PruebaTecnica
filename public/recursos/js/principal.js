const datosCompartidos = { 
  categorias: [], 
  prioridades: [], 
  tecnicos: [], 
};

async function refrescarTodo() { 
  await Promise.all([pintarTablero(), pintarTabla()]); 
}

async function iniciarPagina() { 
  conectarEventosDeLaTabla(); 
  conectarEventosDelFormulario(); 

  try { 
    const [catalogos, tecnicos] = await Promise.all([listarCatalogos(), listarTecnicos()]);
    datosCompartidos.categorias = catalogos.categorias; 
    datosCompartidos.prioridades = catalogos.prioridades; 
    datosCompartidos.tecnicos = tecnicos.data; 
  } catch (error) { 
    mostrarAviso(describirErrorDeApi(error), 'danger'); 
    return; 
  }

  llenarLista(document.getElementById('filtro-prioridad'), datosCompartidos.prioridades.map((prioridad) => ({ valor: prioridad.nombre, texto: prioridad.nombre })), 'Todas');
  llenarLista(document.getElementById('filtro-tecnico'), datosCompartidos.tecnicos.map((tecnico) => ({ valor: tecnico.id, texto: tecnico.nombre })), 'Todos'); 

  await refrescarTodo(); 
}

document.addEventListener('DOMContentLoaded', iniciarPagina); 
