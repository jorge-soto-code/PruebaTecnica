/*
  ARCHIVO: public/recursos/js/tablero.js
  FASE: 6

  QUÉ VA AQUÍ:
    Dibuja el tablero resumen que está encima de la tabla:
      - total de tickets por estado (ABIERTO / EN CURSO / CERRADO)
      - tickets vencidos
      - carga activa por técnico (por ejemplo "Natalia: 5/5")
    Los números los entrega la API (/api/resumen). Esta función se vuelve a
    llamar cada vez que se registra, se cambia un estado o se reasigna, para
    que el tablero y la tabla siempre se refresquen juntos.
*/

function crearTarjetaDeNumero(titulo, valor, claseDelNumero) { // Una tarjeta con un título y un número grande
  const columna = crearElemento('div', 'col-6 col-md-3'); // Columna: 2 por fila en celular, 4 en pantalla grande
  const tarjeta = crearElemento('div', 'card tarjeta-numero h-100'); // Tarjeta de Bootstrap
  const cuerpo = crearElemento('div', 'card-body'); // Interior de la tarjeta
  cuerpo.append(crearElemento('div', 'text-muted small', titulo)); // Título pequeño
  cuerpo.append(crearElemento('div', 'display-6 fw-semibold ' + claseDelNumero, String(valor))); // Número grande
  tarjeta.append(cuerpo); // Mete el interior en la tarjeta
  columna.append(tarjeta); // Mete la tarjeta en la columna
  return columna; // Devuelve la columna lista
}

function crearTarjetaDeCarga(tecnicos) { // Tarjeta con una barra de carga por técnico
  const tarjeta = crearElemento('div', 'card mb-3'); // Tarjeta de Bootstrap
  const cuerpo = crearElemento('div', 'card-body'); // Interior de la tarjeta
  cuerpo.append(crearElemento('h2', 'h6 mb-3', 'Carga activa por técnico (tickets abiertos o en curso)')); // Título de la tarjeta

  const fila = crearElemento('div', 'row g-3'); // Fila que reparte a los técnicos en columnas
  for (const tecnico of tecnicos) { // Recorre cada técnico activo
    const columna = crearElemento('div', 'col-12 col-md-4'); // Una columna por técnico
    const encabezado = crearElemento('div', 'd-flex justify-content-between small'); // Nombre a la izquierda, número a la derecha
    encabezado.append(crearElemento('span', '', tecnico.nombre)); // Nombre del técnico
    encabezado.append(crearElemento('span', 'fw-semibold', tecnico.carga_actual + '/' + tecnico.tope_de_carga)); // Ejemplo: "5/5"

    const porcentaje = Math.min(100, Math.round((tecnico.carga_actual / tecnico.tope_de_carga) * 100)); // Qué tan llena está la barra
    let colorDeLaBarra = 'bg-success'; // Verde: le sobra capacidad
    if (!tecnico.puede_recibir_mas) { // Si ya está en el tope
      colorDeLaBarra = 'bg-danger'; // Rojo: no puede recibir más
    } else if (tecnico.carga_actual >= tecnico.tope_de_carga - 1) { // Si le falta uno para el tope
      colorDeLaBarra = 'bg-warning'; // Amarillo: casi lleno
    }

    const pista = crearElemento('div', 'progress mt-1'); // Fondo de la barra
    const barra = crearElemento('div', 'progress-bar ' + colorDeLaBarra); // La barra de color
    barra.style.width = porcentaje + '%'; // Ancho según la carga
    pista.append(barra); // Mete la barra en su fondo
    columna.append(encabezado, pista); // Mete nombre y barra en la columna
    fila.append(columna); // Mete la columna en la fila
  }

  cuerpo.append(fila); // Mete la fila en la tarjeta
  tarjeta.append(cuerpo); // Mete el interior en la tarjeta
  return tarjeta; // Devuelve la tarjeta lista
}

async function pintarTablero() { // Pide el resumen a la API y dibuja el tablero
  let resumen; // Aquí quedará la respuesta
  try { // Intenta pedir el resumen
    resumen = await obtenerResumen(); // GET /resumen
  } catch (error) { // Si la API falla
    mostrarAviso(describirErrorDeApi(error), 'danger'); // Muestra el motivo
    return; // No dibuja nada
  }

  const tarjetas = crearElemento('div', 'row g-3 mb-3'); // Fila con las cuatro tarjetas de números
  tarjetas.append(crearTarjetaDeNumero('Abiertos', resumen.por_estado['ABIERTO'], 'text-primary')); // Tickets ABIERTO
  tarjetas.append(crearTarjetaDeNumero('En curso', resumen.por_estado['EN CURSO'], 'text-info')); // Tickets EN CURSO
  tarjetas.append(crearTarjetaDeNumero('Cerrados', resumen.por_estado['CERRADO'], 'text-secondary')); // Tickets CERRADO
  tarjetas.append(crearTarjetaDeNumero('Vencidos', resumen.vencidos, resumen.vencidos > 0 ? 'text-danger' : 'text-success')); // Rojo si hay vencidos

  document.getElementById('tablero').replaceChildren(tarjetas, crearTarjetaDeCarga(resumen.carga_por_tecnico)); // Reemplaza el contenido anterior
}
