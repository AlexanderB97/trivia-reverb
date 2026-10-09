<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Trivia en vivo - Host</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    /* Estilos propios (Tailwind resetea tamaños de títulos y botones) */
    :root {
      --fondo: #0f1020;
      --panel: #1b1d36;
      --borde: #2c2f55;
      --texto: #f2f3ff;
      --suave: #9aa0c8;
      --rojo: #e21b3c;
      --azul: #1368ce;
      --amarillo: #d89e00;
      --verde: #26890c;
      --ok: #3ddc84;
      --error: #ff5c74;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      background: radial-gradient(circle at top, #23265a 0%, var(--fondo) 60%);
      color: var(--texto);
      font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }

    header {
      display: flex; flex-wrap: wrap; gap: 12px;
      align-items: center; justify-content: space-between;
      margin-bottom: 24px;
    }
    header h1 { font-size: 28px; font-weight: 800; margin: 0; letter-spacing: .5px; }
    header h1 span { color: var(--amarillo); }
    .acciones { display: flex; flex-wrap: wrap; gap: 10px; }

    .btn {
      font: inherit; font-size: 16px; font-weight: 700;
      padding: 12px 20px; border: 0; border-radius: 12px;
      cursor: pointer; color: #fff;
      transition: transform .1s, opacity .2s;
    }
    .btn:active { transform: scale(.97); }
    .btn:disabled { opacity: .4; cursor: not-allowed; }
    .btn-primario { background: #6c5ce7; }
    .btn-revelar { background: var(--verde); }
    .btn-reiniciar { background: transparent; border: 2px solid var(--borde); color: var(--suave); }

    .aviso { margin: 0 0 16px; padding: 12px 16px; border-radius: 12px; font-weight: 600; }
    .aviso-cargando { background: #2b2f63; color: #c9ccff; }
    .aviso-error { background: rgba(255, 92, 116, .15); border: 1px solid var(--error); color: var(--error); }
    .oculto { display: none !important; }

    .pregunta {
      background: var(--panel); border: 1px solid var(--borde);
      border-radius: 20px; padding: 28px 24px; margin-bottom: 20px; text-align: center;
    }
    .pregunta .numero { color: var(--suave); font-size: 16px; font-weight: 700; margin: 0 0 8px; }
    .pregunta h2 { font-size: clamp(22px, 4vw, 40px); font-weight: 800; line-height: 1.25; margin: 0; }

    .opciones { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
    .opcion {
      display: flex; align-items: center; gap: 14px;
      padding: 22px 18px; border-radius: 16px;
      font-size: clamp(16px, 2.2vw, 24px); font-weight: 700; line-height: 1.3;
      transition: opacity .3s, box-shadow .3s, transform .3s;
    }
    .opcion .forma { font-size: 1.4em; flex-shrink: 0; }
    .op-0 { background: var(--rojo); }
    .op-1 { background: var(--azul); }
    .op-2 { background: var(--amarillo); }
    .op-3 { background: var(--verde); }
    /* Al revelar: la correcta se resalta en verde y las demás se apagan */
    .opcion.apagada { opacity: .25; }
    .opcion.correcta { box-shadow: 0 0 0 5px var(--ok), 0 0 30px var(--ok); transform: scale(1.02); }
    .opcion.correcta::after { content: '✔'; margin-left: auto; font-size: 1.4em; }

    .paneles { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .panel {
      background: var(--panel); border: 1px solid var(--borde);
      border-radius: 16px; padding: 20px;
    }
    .panel h3 { font-size: 18px; font-weight: 800; margin: 0 0 12px; }
    .contador { font-size: 40px; font-weight: 800; color: var(--amarillo); margin: 0 0 8px; }
    .chips { display: flex; flex-wrap: wrap; gap: 8px; list-style: none; margin: 0; padding: 0; }
    .chips li { background: #2b2f63; padding: 6px 12px; border-radius: 999px; font-weight: 600; animation: entrar .3s; }
    @keyframes entrar { from { transform: scale(.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .explicacion { color: #d6d8ff; font-size: 17px; line-height: 1.5; margin: 0; }
    .ranking { list-style: none; margin: 0; padding: 0; }
    .ranking li {
      display: flex; justify-content: space-between; gap: 12px;
      padding: 10px 12px; border-radius: 10px; margin-bottom: 6px;
      background: #24274d; font-weight: 700;
    }
    .ranking li:nth-child(1) { background: linear-gradient(90deg, #b8860b, #24274d); }
    .ranking li:nth-child(2) { background: linear-gradient(90deg, #7d8597, #24274d); }
    .ranking li:nth-child(3) { background: linear-gradient(90deg, #8c5a2b, #24274d); }
    .vacio { color: var(--suave); margin: 0; }

    @media (max-width: 700px) {
      .opciones, .paneles { grid-template-columns: 1fr; }
      .acciones { width: 100%; }
      .acciones .btn { flex: 1; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>Trivia <span>en vivo</span></h1>
    <div class="acciones">
      <button id="btn-nueva" class="btn btn-primario">Nueva pregunta</button>
      <button id="btn-revelar" class="btn btn-revelar" disabled>Revelar</button>
      <button id="btn-reiniciar" class="btn btn-reiniciar">Reiniciar partida</button>
    </div>
  </header>

  <p id="cargando" class="aviso aviso-cargando oculto">Generando pregunta con IA...</p>
  <p id="error" class="aviso aviso-error oculto"></p>

  <section class="pregunta">
    <p id="numero" class="numero">Sin pregunta todavía</p>
    <h2 id="texto">Tocá "Nueva pregunta" para empezar</h2>
  </section>

  <section id="opciones" class="opciones oculto"></section>

  <section class="paneles">
    <div class="panel">
      <h3>Respuestas</h3>
      <p class="contador"><span id="contador">0</span> respondieron</p>
      <ul id="respondieron" class="chips"></ul>
    </div>
    <div class="panel">
      <h3>Ranking</h3>
      <p id="explicacion" class="explicacion oculto"></p>
      <ol id="ranking" class="ranking"></ol>
      <p id="ranking-vacio" class="vacio">Todavía no hay puntos.</p>
    </div>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Formas tipo Kahoot para cada opción (rojo, azul, amarillo, verde)
    const FORMAS = ['▲', '◆', '●', '■'];
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    // Referencias a los elementos de la página
    const btnNueva = document.getElementById('btn-nueva');
    const btnRevelar = document.getElementById('btn-revelar');
    const btnReiniciar = document.getElementById('btn-reiniciar');
    const cargando = document.getElementById('cargando');
    const error = document.getElementById('error');
    const numero = document.getElementById('numero');
    const texto = document.getElementById('texto');
    const opciones = document.getElementById('opciones');
    const contador = document.getElementById('contador');
    const respondieron = document.getElementById('respondieron');
    const explicacion = document.getElementById('explicacion');
    const ranking = document.getElementById('ranking');
    const rankingVacio = document.getElementById('ranking-vacio');

    // Nombres que ya respondieron en esta ronda (para no repetirlos en la lista)
    let nombres = [];
    // Número de la pregunta que se está mostrando
    let numeroActual = null;

    // Hace un POST con fetch mandando el token CSRF y pidiendo JSON.
    // Si el servidor responde con error, lanza una excepción con su mensaje.
    async function post(url, datos = {}) {
      const respuesta = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify(datos),
      });
      const json = await respuesta.json().catch(() => ({}));
      if (!respuesta.ok) {
        throw new Error(json.message || 'Error ' + respuesta.status);
      }
      return json;
    }

    function mostrarError(mensaje) {
      error.textContent = mensaje;
      error.classList.toggle('oculto', !mensaje);
    }

    // Dibuja una pregunta nueva y limpia lo de la ronda anterior
    function mostrarPregunta(n, textoPregunta, listaOpciones) {
      // La misma pregunta llega por fetch y por Echo: si ya está en pantalla, no la redibujamos
      if (n === numeroActual) return;
      numeroActual = n;

      numero.textContent = 'Pregunta ' + n;
      texto.textContent = textoPregunta;

      opciones.innerHTML = '';
      listaOpciones.forEach((op, i) => {
        const div = document.createElement('div');
        div.className = 'opcion op-' + i;
        div.innerHTML = '<span class="forma">' + FORMAS[i] + '</span><span></span>';
        div.lastChild.textContent = op; // textContent evita inyectar HTML
        opciones.appendChild(div);
      });
      opciones.classList.remove('oculto');

      nombres = [];
      respondieron.innerHTML = '';
      contador.textContent = '0';
      explicacion.classList.add('oculto');
      btnRevelar.disabled = false;
    }

    // Agrega un nombre a la lista de "quiénes respondieron"
    function agregarRespuesta(nombre, cantidad) {
      if (!nombres.includes(nombre)) {
        nombres.push(nombre);
        const li = document.createElement('li');
        li.textContent = nombre;
        respondieron.appendChild(li);
      }
      contador.textContent = cantidad ?? nombres.length;
    }

    function mostrarRanking(lista) {
      ranking.innerHTML = '';
      lista.forEach((jugador, i) => {
        const li = document.createElement('li');
        li.innerHTML = '<span></span><span></span>';
        li.firstChild.textContent = (i + 1) + '. ' + jugador.nombre;
        li.lastChild.textContent = jugador.puntos + ' pts';
        ranking.appendChild(li);
      });
      rankingVacio.classList.toggle('oculto', lista.length > 0);
    }

    // Resalta la correcta, apaga las demás y muestra explicación + ranking
    function mostrarResultado(correcta, textoExplicacion, lista) {
      [...opciones.children].forEach((div, i) => {
        div.classList.toggle('correcta', i === correcta);
        div.classList.toggle('apagada', i !== correcta);
      });
      explicacion.textContent = textoExplicacion;
      explicacion.classList.toggle('oculto', !textoExplicacion);
      mostrarRanking(lista);
      btnRevelar.disabled = true;
    }

    // ---- Botones ----

    btnNueva.addEventListener('click', async () => {
      mostrarError('');
      btnNueva.disabled = true;
      btnRevelar.disabled = true;
      cargando.classList.remove('oculto');
      try {
        const p = await post("{{ route('trivia.pregunta') }}");
        // También llega por Echo, pero así se ve aunque el websocket tarde
        mostrarPregunta(p.numero, p.texto, p.opciones);
      } catch (e) {
        mostrarError(e.message);
      } finally {
        btnNueva.disabled = false;
        cargando.classList.add('oculto');
      }
    });

    btnRevelar.addEventListener('click', async () => {
      mostrarError('');
      btnRevelar.disabled = true;
      try {
        const r = await post("{{ route('trivia.revelar') }}");
        mostrarResultado(r.correcta, r.explicacion, r.ranking);
      } catch (e) {
        mostrarError(e.message);
      }
    });

    btnReiniciar.addEventListener('click', async () => {
      if (!confirm('¿Reiniciar la partida? Se borran todos los puntajes.')) return;
      mostrarError('');
      try {
        await post("{{ route('trivia.reiniciar') }}");
        numero.textContent = 'Sin pregunta todavía';
        texto.textContent = 'Tocá "Nueva pregunta" para empezar';
        opciones.classList.add('oculto');
        opciones.innerHTML = '';
        numeroActual = null;
        nombres = [];
        respondieron.innerHTML = '';
        contador.textContent = '0';
        explicacion.classList.add('oculto');
        mostrarRanking([]);
        btnRevelar.disabled = true;
      } catch (e) {
        mostrarError(e.message);
      }
    });

    // ---- Eventos en vivo (Reverb + Echo) ----
    // El punto delante del nombre indica que usamos el nombre de broadcastAs()

    window.Echo.channel('trivia')
      .listen('.PreguntaLanzada', (e) => {
        mostrarPregunta(e.numero, e.texto, e.opciones);
      })
      .listen('.RespuestaRecibida', (e) => {
        agregarRespuesta(e.nombre, e.cantidad);
      })
      .listen('.ResultadoRevelado', (e) => {
        mostrarResultado(e.correcta, e.explicacion, e.ranking);
      });

    // ---- Al cargar: recuperar el estado por si el host recargó la página ----
    fetch("{{ route('trivia.estado') }}", { headers: { 'Accept': 'application/json' } })
      .then((r) => r.json())
      .then((estado) => {
        if (estado.pregunta) {
          mostrarPregunta(estado.numero, estado.pregunta.texto, estado.pregunta.opciones);
          estado.respondieron.forEach((n) => agregarRespuesta(String(n)));
        }
        mostrarRanking(estado.ranking);
      })
      .catch(() => mostrarError('No se pudo cargar el estado de la partida.'));
  });
</script>
</body>
</html>
