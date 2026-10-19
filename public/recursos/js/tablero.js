function crearTarjetaDeNumero(titulo, valor, claseDelNumero) { 
  const columna = crearElemento('div', 'col-6 col-md-3'); 
  const tarjeta = crearElemento('div', 'card tarjeta-numero h-100'); 
  const cuerpo = crearElemento('div', 'card-body'); 
  cuerpo.append(crearElemento('div', 'text-muted small', titulo)); 
  cuerpo.append(crearElemento('div', 'display-6 fw-semibold ' + claseDelNumero, String(valor))); 
  tarjeta.append(cuerpo); 
  columna.append(tarjeta); 
  return columna; 
}

function crearTarjetaDeCarga(tecnicos) { 
  const tarjeta = crearElemento('div', 'card mb-3'); 
  const cuerpo = crearElemento('div', 'card-body'); 
  cuerpo.append(crearElemento('h2', 'h6 mb-3', 'Carga activa por técnico (tickets abiertos o en curso)')); 

  const fila = crearElemento('div', 'row g-3'); 
  for (const tecnico of tecnicos) { 
    const columna = crearElemento('div', 'col-12 col-md-4'); 
    const encabezado = crearElemento('div', 'd-flex justify-content-between small'); 
    encabezado.append(crearElemento('span', '', tecnico.nombre)); 
    encabezado.append(crearElemento('span', 'fw-semibold', tecnico.carga_actual + '/' + tecnico.tope_de_carga)); 

    const porcentaje = Math.min(100, Math.round((tecnico.carga_actual / tecnico.tope_de_carga) * 100)); 
    let colorDeLaBarra = 'bg-success'; 
    if (!tecnico.puede_recibir_mas) {
      colorDeLaBarra = 'bg-danger'; 
    } else if (tecnico.carga_actual >= tecnico.tope_de_carga - 1) { 
      colorDeLaBarra = 'bg-warning'; 
    }

    const pista = crearElemento('div', 'progress mt-1'); 
    const barra = crearElemento('div', 'progress-bar ' + colorDeLaBarra); 
    barra.style.width = porcentaje + '%'; 
    pista.append(barra); 
    columna.append(encabezado, pista); 
    fila.append(columna); 
  }

  cuerpo.append(fila); 
  tarjeta.append(cuerpo); 
  return tarjeta; 
}

async function pintarTablero() { 
  let resumen; 
  try { 
    resumen = await obtenerResumen(); 
  } catch (error) { 
    mostrarAviso(describirErrorDeApi(error), 'danger'); 
    return; 
  }

  const tarjetas = crearElemento('div', 'row g-3 mb-3'); 
  tarjetas.append(crearTarjetaDeNumero('Abiertos', resumen.por_estado['ABIERTO'], 'text-primary')); 
  tarjetas.append(crearTarjetaDeNumero('En curso', resumen.por_estado['EN CURSO'], 'text-info')); 
  tarjetas.append(crearTarjetaDeNumero('Cerrados', resumen.por_estado['CERRADO'], 'text-secondary')); 
  tarjetas.append(crearTarjetaDeNumero('Vencidos', resumen.vencidos, resumen.vencidos > 0 ? 'text-danger' : 'text-success')); 

  document.getElementById('tablero').replaceChildren(tarjetas, crearTarjetaDeCarga(resumen.carga_por_tecnico)); 
}
