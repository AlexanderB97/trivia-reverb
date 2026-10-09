<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Trivia en vivo - Jugar</title>
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
    html, body { height: 100%; }
    body {
      margin: 0;
      background: radial-gradient(circle at top, #23265a 0%, var(--fondo) 60%);
      color: var(--texto);
      font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
      -webkit-tap-highlight-color: transparent;
    }
    .wrap {
      max-width: 640px; min-height: 100%; margin: 0 auto;
      padding: 16px; display: flex; flex-direction: column;
    }
    .barra {
      display: flex; justify-content: space-between; align-items: center;
      gap: 12px; margin-bottom: 16px; color: var(--suave); font-weight: 600;
    }
    .barra strong { color: var(--texto); }
    .link {
      font: inherit; font-size: 14px; background: none; border: 0; padding: 0;
      color: var(--suave); text-decoration: underline; cursor: pointer;
    }

    .pantalla { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 16px; }
    .oculto { display: none !important; }

    h1 { font-size: 32px; font-weight: 800; margin: 0; text-align: center; }
    h1 span { color: var(--amarillo); }
    .centro { text-align: center; }
    .suave { color: var(--suave); font-size: 18px; margin: 0; }

    input {
      font: inherit; font-size: 20px; width: 100%;
      padding: 16px; border-radius: 14px;
      border: 2px solid var(--borde); background: var(--panel); color: var(--texto);
      outline: none;
    }
    input:focus { border-color: #6c5ce7; }
    .btn {
      font: inherit; font-size: 20px; font-weight: 800; width: 100%;
      padding: 16px; border: 0; border-radius: 14px;
      background: #6c5ce7; color: #fff; cursor: pointer;
    }
    .btn:disabled { opacity: .5; }

    /* Animación de "esperando" */
    .puntos { display: inline-flex; gap: 8px; justify-content: center; }
    .puntos i { width: 14px; height: 14px; border-radius: 50%; background: var(--amarillo); animation: rebote 1s infinite; }
    .puntos i:nth-child(2) { animation-delay: .15s; background: var(--azul); }
    .puntos i:nth-child(3) { animation-delay: .3s; background: var(--rojo); }
    @keyframes rebote { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

    .pregunta {
      background: var(--panel); border: 1px solid var(--borde);
      border-radius: 16px; padding: 18px; text-align: center;
    }
    .pregunta .numero { color: var(--suave); font-size: 14px; font-weight: 700; margin: 0 0 6px; }
    .pregunta h2 { font-size: 20px; font-weight: 800; line-height: 1.3; margin: 0; }

    .opciones { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .opcion {
      font: inherit; color: #fff; border: 0; border-radius: 16px; cursor: pointer;
      min-height: 120px; padding: 14px;
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
      font-size: 16px; font-weight: 700; line-height: 1.25; text-align: center;
      box-shadow: 0 6px 0 rgba(0, 0, 0, .35);
      transition: transform .1s, opacity .2s;
    }
    .opcion .forma { font-size: 34px; }
    .opcion:active:not(:disabled) { transform: translateY(4px); box-shadow: 0 2px 0 rgba(0, 0, 0, .35); }
    .opcion:disabled { cursor: default; opacity: .3; }
    .opcion.elegida { opacity: 1; outline: 4px solid #fff; outline-offset: -4px; }
    .op-0 { background: var(--rojo); }
    .op-1 { background: var(--azul); }
    .op-2 { background: var(--amarillo); }
    .op-3 { background: var(--verde); }

    .mensaje { margin: 0; padding: 12px 16px; border-radius: 12px; font-weight: 600; text-align: center; }
    .mensaje-info { background: #2b2f63; color: #c9ccff; }
    .mensaje-error { background: rgba(255, 92, 116, .15); border: 1px solid var(--error); color: var(--error); }

    .resultado {
      border-radius: 20px; padding: 28px 20px; text-align: center;
      background: var(--panel); border: 2px solid var(--borde);
    }
    .resultado.bien { border-color: var(--ok); background: rgba(61, 220, 132, .12); }
    .resultado.mal { border-color: var(--error); background: rgba(255, 92, 116, .12); }
    .resultado h2 { font-size: 30px; font-weight: 800; margin: 0 0 10px; }
    .resultado .era { font-size: 18px; font-weight: 600; margin: 0 0 16px; }
    .marcador { display: flex; justify-content: center; gap: 28px; }
    .marcador div { font-size: 14px; color: var(--suave); font-weight: 600; }
    .marcador b { display: block; font-size: 36px; color: var(--texto); }
    .explicacion { color: #d6d8ff; font-size: 16px; line-height: 1.5; margin: 0; text-align: center; }

    @media (max-width: 380px) {
      .opcion { min-height: 100px; font-size: 14px; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <div id="barra" class="barra oculto">
    <span>Jugando como <strong id="mi-nombre"></strong></span>
    <button id="btn-cambiar" class="link">Cambiar nombre</button>
  </div>

  <!-- 1) Ingresar nombre -->
  <form id="pantalla-nombre" class="pantalla">
    <h1>Trivia <span>en vivo</span></h1>
    <p class="suave centro">Laravel y bases de datos</p>
    <input id="input-nombre" type="text" maxlength="30" placeholder="Tu nombre" autocomplete="off" required>
    <button class="btn" type="submit">Entrar</button>
  </form>

  <!-- 2) Esperando pregunta -->
  <section id="pantalla-espera" class="pantalla centro oculto">
    <div class="puntos"><i></i><i></i><i></i></div>
    <h1>Esperando pregunta...</h1>
    <p class="suave">Mirá la pantalla grande</p>
  </section>

  <!-- 3) Pregunta con las 4 opciones -->
  <section id="pantalla-pregunta" class="pantalla oculto">
    <div class="pregunta">
      <p id="numero" class="numero"></p>
      <h2 id="texto"></h2>
    </div>
    <div id="opciones" class="opciones"></div>
    <p id="mensaje" class="mensaje oculto"></p>
  </section>

  <!-- 4) Resultado de la ronda -->
  <section id="pantalla-resultado" class="pantalla oculto">
    <div id="resultado" class="resultado">
      <h2 id="resultado-titulo"></h2>
      <p id="resultado-era" class="era"></p>
      <div class="marcador">
        <div><b id="mis-puntos">0</b>puntos</div>
        <div><b id="mi-posicion">-</b>posición</div>
      </div>
    </div>
    <p id="explicacion" class="explicacion"></p>
    <p class="suave centro">Esperando la próxima pregunta...</p>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const FORMAS = ['▲', '◆', '●', '■'];
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    // Estado del jugador en el navegador
    let nombre = sessionStorage.getItem('trivia.nombre') || '';
    let opcionesActuales = [];   // textos de las 4 opciones de la pregunta actual
    let miEleccion = null;       // índice (0-3) que tocó el jugador, o null si no respondió

    // Referencias a los elementos
    const pantallas = {
      nombre: document.getElementById('pantalla-nombre'),
      espera: document.getElementById('pantalla-espera'),
      pregunta: document.getElementById('pantalla-pregunta'),
      resultado: document.getElementById('pantalla-resultado'),
    };
    const barra = document.getElementById('barra');
    const miNombre = document.getElementById('mi-nombre');
    const inputNombre = document.getElementById('input-nombre');
    const numero = document.getElementById('numero');
    const texto = document.getElementById('texto');
    const opciones = document.getElementById('opciones');
    const mensaje = document.getElementById('mensaje');

    // Muestra solo una de las pantallas
    function mostrarPantalla(cual) {
      Object.entries(pantallas).forEach(([clave, el]) => el.classList.toggle('oculto', clave !== cual));
      barra.classList.toggle('oculto', cual === 'nombre');
    }

    function mostrarMensaje(textoMensaje, tipo = 'info') {
      mensaje.textContent = textoMensaje;
      mensaje.className = 'mensaje mensaje-' + tipo;
      mensaje.classList.toggle('oculto', !textoMensaje);
    }

    // Bloquea los 4 botones y marca el que eligió el jugador
    function bloquearOpciones(elegida) {
      [...opciones.children].forEach((btn, i) => {
        btn.disabled = true;
        btn.classList.toggle('elegida', i === elegida);
      });
    }

    // Dibuja la pregunta con 4 botones grandes de colores
    function mostrarPregunta(n, textoPregunta, listaOpciones) {
      opcionesActuales = listaOpciones;
      miEleccion = null;
      numero.textContent = 'Pregunta ' + n;
      texto.textContent = textoPregunta;
      mostrarMensaje('');

      opciones.innerHTML = '';
      listaOpciones.forEach((op, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'opcion op-' + i;
        btn.innerHTML = '<span class="forma">' + FORMAS[i] + '</span><span></span>';
        btn.lastChild.textContent = op; // textContent evita inyectar HTML
        btn.addEventListener('click', () => responder(i));
        opciones.appendChild(btn);
      });

      mostrarPantalla('pregunta');
    }

    // Manda la respuesta al servidor
    async function responder(i) {
      if (miEleccion !== null) return;
      miEleccion = i;            // la recordamos para compararla al revelar
      bloquearOpciones(i);
      mostrarMensaje('Respuesta enviada. ¡Esperá el resultado!');

      try {
        const respuesta = await fetch("{{ route('trivia.responder') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
          },
          body: JSON.stringify({ nombre: nombre, opcion: i }),
        });
        const json = await respuesta.json().catch(() => ({}));

        if (!respuesta.ok) {
          mostrarMensaje(json.message || 'Error ' + respuesta.status, 'error');
          // 422 = la pregunta se cerró o ya respondiste: no se puede volver a intentar.
          // Con otros errores (red, servidor ocupado) dejamos reintentar.
          if (respuesta.status !== 422) {
            miEleccion = null;
            [...opciones.children].forEach((btn) => { btn.disabled = false; btn.classList.remove('elegida'); });
          }
        }
      } catch (e) {
        mostrarMensaje('No se pudo enviar la respuesta. Revisá la conexión y tocá de nuevo.', 'error');
        miEleccion = null;
        [...opciones.children].forEach((btn) => { btn.disabled = false; btn.classList.remove('elegida'); });
      }
    }

    // Muestra si acertó, la correcta, sus puntos y su posición
    function mostrarResultado(correcta, textoExplicacion, ranking) {
      const caja = document.getElementById('resultado');
      const titulo = document.getElementById('resultado-titulo');
      const era = document.getElementById('resultado-era');

      if (miEleccion === correcta) {
        caja.className = 'resultado bien';
        titulo.textContent = '✅ ¡Correcto!';
        era.textContent = opcionesActuales[correcta] ?? '';
      } else {
        caja.className = 'resultado mal';
        titulo.textContent = miEleccion === null ? '⏱️ No respondiste' : '❌ Incorrecto';
        era.textContent = 'Era: ' + (opcionesActuales[correcta] ?? 'opción ' + (correcta + 1));
      }

      // Buscamos al jugador en el ranking (viene ordenado de mayor a menor)
      const posicion = ranking.findIndex((j) => String(j.nombre) === nombre);
      document.getElementById('mis-puntos').textContent = posicion >= 0 ? ranking[posicion].puntos : 0;
      document.getElementById('mi-posicion').textContent = posicion >= 0 ? (posicion + 1) + '° de ' + ranking.length : '-';
      document.getElementById('explicacion').textContent = textoExplicacion;

      mostrarPantalla('resultado');
    }

    // Pide al servidor el estado actual (para sumarse tarde o tras recargar)
    async function cargarEstado() {
      try {
        const r = await fetch("{{ route('trivia.estado') }}?nombre=" + encodeURIComponent(nombre), {
          headers: { 'Accept': 'application/json' },
        });
        const estado = await r.json();

        if (estado.pregunta) {
          mostrarPregunta(estado.numero, estado.pregunta.texto, estado.pregunta.opciones);
          // Si ya había respondido antes de recargar, dejamos los botones bloqueados
          if (estado.miOpcion !== null) {
            miEleccion = estado.miOpcion;
            bloquearOpciones(miEleccion);
            mostrarMensaje('Ya respondiste. ¡Esperá el resultado!');
          }
        } else {
          mostrarPantalla('espera');
        }
      } catch (e) {
        mostrarPantalla('espera');
      }
    }

    // Arranca el juego con el nombre guardado
    function entrar() {
      miNombre.textContent = nombre;
      cargarEstado();
    }

    // ---- Formulario de nombre ----
    pantallas.nombre.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const valor = inputNombre.value.trim();
      if (!valor) return;
      nombre = valor;
      sessionStorage.setItem('trivia.nombre', nombre);
      entrar();
    });

    document.getElementById('btn-cambiar').addEventListener('click', () => {
      sessionStorage.removeItem('trivia.nombre');
      nombre = '';
      inputNombre.value = '';
      mostrarPantalla('nombre');
      inputNombre.focus();
    });

    // ---- Eventos en vivo (Reverb + Echo) ----
    window.Echo.channel('trivia')
      .listen('.PreguntaLanzada', (e) => {
        if (!nombre) return; // todavía no ingresó el nombre
        mostrarPregunta(e.numero, e.texto, e.opciones);
      })
      .listen('.RespuestaRecibida', (e) => {
        // El jugador no necesita hacer nada con esto (lo usa el host)
      })
      .listen('.ResultadoRevelado', (e) => {
        if (!nombre) return;
        mostrarResultado(e.correcta, e.explicacion, e.ranking);
      });

    // ---- Al cargar ----
    if (nombre) {
      entrar();
    } else {
      mostrarPantalla('nombre');
      inputNombre.focus();
    }
  });
</script>
</body>
</html>
