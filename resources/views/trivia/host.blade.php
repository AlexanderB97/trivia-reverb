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
      --violeta: #6c5ce7;
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
    .oculto { display: none !important; }

    header {
      display: flex; flex-wrap: wrap; gap: 12px;
      align-items: center; justify-content: space-between;
      margin-bottom: 20px;
    }
    header h1 { font-size: 28px; font-weight: 800; margin: 0; letter-spacing: .5px; }
    header h1 span { color: var(--amarillo); }
    .acciones { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .acciones form { margin: 0; }

    .btn {
      font: inherit; font-size: 16px; font-weight: 700;
      padding: 12px 20px; border: 0; border-radius: 12px;
      cursor: pointer; color: #fff;
      transition: transform .1s, opacity .2s;
    }
    .btn:active { transform: scale(.97); }
    .btn:disabled { opacity: .4; cursor: not-allowed; }
    .btn-primario { background: var(--violeta); }
    .btn-grande { font-size: 20px; padding: 16px 28px; }
    .btn-revelar { background: var(--verde); }
    .btn-pausa { background: var(--amarillo); }
    .btn-borde { background: transparent; border: 2px solid var(--borde); color: var(--suave); }

    .aviso { margin: 0 0 16px; padding: 12px 16px; border-radius: 12px; font-weight: 600; }
    .aviso-info { background: #2b2f63; color: #c9ccff; }
    .aviso-error { background: rgba(255, 92, 116, .15); border: 1px solid var(--error); color: var(--error); }

    .panel {
      background: var(--panel); border: 1px solid var(--borde);
      border-radius: 16px; padding: 20px;
    }
    .panel h3 { font-size: 18px; font-weight: 800; margin: 0 0 12px; }

    /* --- Configuración y sala de espera --- */
    .config { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr); gap: 16px; }

    /* --- QR para unirse --- */
    .sala-cuerpo { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
    .sala-jugadores { flex: 1; min-width: 200px; }
    .qr-grande { display: flex; flex-direction: column; align-items: center; text-align: center; }
    .qr-titulo { font-size: 26px; font-weight: 800; margin: 0 0 12px; }
    /* Fondo y margen blancos aunque la página sea oscura: así los celulares lo leen bien */
    .qr { display: block; background: #fff; padding: 14px; border-radius: 12px; image-rendering: pixelated; }
    /* 340 px con el margen blanco incluido: el código en sí queda en ~310 px */
    .qr-grande .qr { width: 340px; max-width: 100%; height: auto; aspect-ratio: 1; }
    .qr-grande .url-jugar { font-size: 24px; margin: 12px 0 0; }
    .aviso-local { background: rgba(216, 158, 0, .18); border: 1px solid var(--amarillo); color: #ffd56b; }

    /* QR chico en la esquina durante la partida */
    .qr-mini {
      position: fixed; right: 16px; bottom: 16px; z-index: 20;
      display: flex; flex-direction: column; align-items: center; gap: 4px;
      background: var(--panel); border: 1px solid var(--borde); border-radius: 14px;
      padding: 8px; cursor: pointer; color: var(--suave); font: inherit; font-size: 13px; font-weight: 700;
    }
    .qr-mini .qr { width: 96px; height: 96px; padding: 6px; border-radius: 8px; }

    /* Modal con el QR grande */
    .qr-modal {
      position: fixed; inset: 0; z-index: 30; padding: 16px;
      display: flex; align-items: center; justify-content: center;
      background: rgba(10, 11, 25, .92); cursor: pointer;
    }
    .qr-modal .qr { width: min(70vh, 90vw); height: auto; aspect-ratio: 1; }
    .campos { display: flex; flex-direction: column; gap: 14px; }
    .campo { display: flex; justify-content: space-between; align-items: center; gap: 12px; font-weight: 600; }
    .campo input {
      font: inherit; font-size: 20px; font-weight: 700; width: 100px; text-align: center;
      padding: 10px; border-radius: 10px; outline: none;
      border: 2px solid var(--borde); background: var(--fondo); color: var(--texto);
    }
    .campo input:focus { border-color: var(--violeta); }
    .banco { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; font-weight: 600; }
    .banco b { color: var(--ok); font-size: 20px; }
    /* En rojo si no alcanzan para la partida configurada */
    .banco.insuficiente { color: var(--error); }
    .banco.insuficiente b { color: var(--error); }
    .url-jugar { font-size: 20px; font-weight: 800; color: var(--amarillo); word-break: break-all; margin: 0 0 14px; }
    .qr-modal .url-jugar { font-size: clamp(20px, 3vw, 32px); }

    /* --- Pregunta --- */
    .pregunta {
      position: relative;
      background: var(--panel); border: 1px solid var(--borde);
      border-radius: 20px; padding: 28px 24px 34px; margin-bottom: 20px; text-align: center;
      overflow: hidden;
    }
    .pregunta .numero { color: var(--suave); font-size: 16px; font-weight: 700; margin: 0 0 8px; }
    .pregunta .tema { color: var(--amarillo); font-size: 14px; font-weight: 600; margin: 0 70px 10px; }
    .pregunta h2 { font-size: clamp(22px, 4vw, 40px); font-weight: 800; line-height: 1.25; margin: 0; }
    .reloj {
      position: absolute; top: 14px; right: 16px;
      width: 64px; height: 64px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      background: var(--violeta); font-size: 26px; font-weight: 800;
    }
    .reloj.urgente { background: var(--rojo); }
    .barra-tiempo { position: absolute; left: 0; bottom: 0; height: 8px; background: var(--amarillo); transition: width .25s linear; }

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
    .contador { font-size: 40px; font-weight: 800; color: var(--amarillo); margin: 0 0 8px; }
    .contador small { font-size: 20px; color: var(--suave); }
    .chips { display: flex; flex-wrap: wrap; gap: 8px; list-style: none; margin: 0; padding: 0; }
    .chips li { background: #2b2f63; padding: 6px 12px; border-radius: 999px; font-weight: 600; animation: entrar .3s; }
    @keyframes entrar { from { transform: scale(.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    /* --- Lista de jugadores: 🟢 conectado / ⚪ desconectado y botón para quitar --- */
    .conectados { font-size: 14px; font-weight: 600; color: var(--suave); margin-left: 6px; }
    .panel-jugadores { margin-top: 16px; }
    /* Sin animación: la lista se redibuja entera cada vez que alguien se conecta o desconecta */
    .chips.jugadores li { display: inline-flex; align-items: center; gap: 6px; padding: 4px 6px 4px 10px; animation: none; }
    .jugador .estado { font-size: 12px; }
    .jugador.desconectado .nombre { color: var(--suave); }
    .btn-quitar {
      font: inherit; font-size: 14px; line-height: 1; width: 24px; height: 24px;
      border: 0; border-radius: 50%; background: transparent; color: var(--suave); cursor: pointer;
    }
    .btn-quitar:hover, .btn-quitar:focus-visible { background: var(--error); color: #fff; }
    /* Confirmación dentro de la página: "¿Quitar a Ana?  [Sí, quitar] [No]" */
    .chips.jugadores li.confirmando { background: rgba(255, 92, 116, .15); outline: 1px solid var(--error); padding-right: 10px; }
    .btn-chip {
      font: inherit; font-size: 13px; font-weight: 700; padding: 3px 10px;
      border: 0; border-radius: 999px; cursor: pointer; color: #fff; background: var(--borde);
    }
    .btn-chip.peligro { background: var(--rojo); }

    .explicacion { color: #d6d8ff; font-size: 17px; line-height: 1.5; margin: 0 0 12px; }
    .ranking { list-style: none; margin: 0; padding: 0; }
    .ranking li {
      display: flex; justify-content: space-between; gap: 12px;
      padding: 10px 12px; border-radius: 10px; margin-bottom: 6px;
      background: #24274d; font-weight: 700;
    }
    /* Color según el puesto (no la posición en la lista), así los empates se ven iguales */
    .ranking li.puesto-1 { background: linear-gradient(90deg, #b8860b, #24274d); }
    .ranking li.puesto-2 { background: linear-gradient(90deg, #7d8597, #24274d); }
    .ranking li.puesto-3 { background: linear-gradient(90deg, #8c5a2b, #24274d); }
    .ganados { color: var(--ok); font-size: 14px; margin-left: 8px; }
    .vacio { color: var(--suave); margin: 0; }

    /* --- Podio --- */
    .podio-titulo { font-size: clamp(28px, 5vw, 48px); font-weight: 800; text-align: center; margin: 10px 0 30px; }
    .podio { display: flex; justify-content: center; align-items: flex-end; gap: 14px; margin-bottom: 28px; }
    .escalon { width: 30%; max-width: 220px; text-align: center; }
    .escalon .medalla { font-size: 48px; }
    .escalon .quien { font-size: 22px; font-weight: 800; margin: 6px 0 4px; word-break: break-word; }
    .escalon .pts { color: var(--suave); font-weight: 700; margin-bottom: 8px; }
    .escalon .bloque {
      border-radius: 14px 14px 0 0; display: flex; align-items: flex-start; justify-content: center;
      padding-top: 12px; font-size: 36px; font-weight: 800;
    }
    .esc-1 .bloque { height: 200px; background: linear-gradient(#e0b21c, #8a6a00); }
    .esc-2 .bloque { height: 140px; background: linear-gradient(#a7b0c2, #5b6375); }
    .esc-3 .bloque { height: 100px; background: linear-gradient(#c98a4b, #7a4f22); }
    .centro { text-align: center; }

    /* Aviso flotante (sin conexión con el servidor), igual al de /jugar */
    .aviso-flotante {
      position: fixed; left: 16px; right: 16px; bottom: 16px; z-index: 40;
      max-width: 608px; margin: 0 auto;
      padding: 14px 16px; border-radius: 14px;
      background: var(--panel); border: 2px solid var(--amarillo); color: var(--texto);
      font-weight: 600; line-height: 1.35; text-align: center;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .55);
    }

    @media (max-width: 760px) {
      .opciones, .paneles, .config { grid-template-columns: 1fr; }
      .acciones { width: 100%; }
      .acciones .btn { flex: 1; }
      .reloj { width: 48px; height: 48px; font-size: 20px; }
      .pregunta { padding-top: 70px; }
      .pregunta .tema { margin: 0 0 10px; }
      .escalon .medalla { font-size: 34px; }
      .escalon .quien { font-size: 16px; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <header>
    <h1>Trivia <span>en vivo</span></h1>
    <div class="acciones">
      <button id="btn-pausa" class="btn btn-pausa" disabled>Pausar</button>
      <button id="btn-saltar" class="btn btn-revelar" disabled>Saltar a revelar</button>
      <button id="btn-reiniciar" class="btn btn-borde">Reiniciar partida</button>
      <form method="POST" action="{{ route('trivia.salir') }}">
        @csrf
        <button class="btn btn-borde" type="submit">Salir</button>
      </form>
    </div>
  </header>

  <p id="info" class="aviso aviso-info oculto"></p>
  <p id="error" class="aviso aviso-error oculto"></p>

  <!-- 1) Configuración + sala de espera -->
  <section id="seccion-config" class="config">
    <div class="panel">
      <h3>Configurar partida</h3>
      <div class="campos">
        <label class="campo">Cantidad de preguntas
          <input id="cfg-total" type="number" min="1" max="50" value="10">
        </label>
        <label class="campo">Segundos para responder
          <input id="cfg-segundos" type="number" min="5" max="120" value="20">
        </label>
        <label class="campo">Segundos mostrando el resultado
          <input id="cfg-resultado" type="number" min="3" max="60" value="8">
        </label>
        <div id="banco" class="banco">
          <span>Banco: <b id="banco-n">0</b> preguntas disponibles</span>
          <button id="btn-generar-lote" class="btn btn-borde" type="button">Generar 10 más</button>
        </div>
        <button id="btn-empezar" class="btn btn-primario btn-grande">Empezar partida</button>
      </div>
    </div>
    <div class="panel sala">
      @if ($urlEsLocal)
        <p class="aviso aviso-local">Ojo: esta dirección no funciona desde los celulares. Configurá REVERB_HOST con la IP de la notebook (ipconfig) o TRIVIA_URL_JUGAR en el .env.</p>
      @endif
      <div class="sala-cuerpo">
        <!-- QR grande para escanear desde el fondo del aula -->
        <div class="qr-grande">
          <p class="qr-titulo">Escaneá para jugar</p>
          <img class="qr" src="{{ $qrJugar }}" alt="Código QR para entrar a jugar">
          <p class="url-jugar">{{ $urlJugar }}</p>
        </div>
        <div class="sala-jugadores">
          <h3>Jugadores (<span class="js-cantidad">0</span>) <small class="conectados js-conectados"></small></h3>
          <ul class="chips jugadores js-lista-jugadores"></ul>
        </div>
      </div>
    </div>
  </section>

  <!-- 2) Juego en curso -->
  <section id="seccion-juego" class="oculto">
    <div class="pregunta">
      <div id="reloj" class="reloj oculto">0</div>
      <p id="numero" class="numero"></p>
      <p id="tema" class="tema oculto"></p>
      <h2 id="texto"></h2>
      <div id="barra-tiempo" class="barra-tiempo" style="width: 0"></div>
    </div>

    <div id="opciones" class="opciones"></div>

    <div class="paneles">
      <div class="panel">
        <h3>Respuestas</h3>
        <p class="contador"><span id="contador">0</span> <small>de <span id="total-jugadores">0</span> conectados respondieron</small></p>
        <ul id="respondieron" class="chips"></ul>
      </div>
      <div class="panel">
        <h3>Ranking</h3>
        <p id="explicacion" class="explicacion oculto"></p>
        <ol id="ranking" class="ranking"></ol>
        <p id="ranking-vacio" class="vacio">Todavía no hay puntos.</p>
      </div>
    </div>

    <!-- La misma lista de la sala de espera, para ver quién sigue conectado durante la partida -->
    <div class="panel panel-jugadores">
      <h3>Jugadores (<span class="js-cantidad">0</span>) <small class="conectados js-conectados"></small></h3>
      <ul class="chips jugadores js-lista-jugadores"></ul>
    </div>
  </section>

  <!-- 3) Podio final -->
  <section id="seccion-podio" class="oculto">
    <h2 class="podio-titulo">🏆 ¡Fin de la partida!</h2>
    <div id="podio" class="podio"></div>
    <div class="panel">
      <h3>Ranking final</h3>
      <ol id="ranking-final" class="ranking"></ol>
    </div>
    <p class="centro"><button id="btn-nueva-partida" class="btn btn-primario btn-grande" style="margin-top: 20px">Nueva partida</button></p>
  </section>
</div>

<!-- QR chico durante la partida (para los que llegan tarde): al tocarlo se agranda -->
<button id="qr-mini" class="qr-mini oculto" type="button" title="Mostrar QR para unirse">
  <img class="qr" src="{{ $qrJugar }}" alt="QR para unirse">
  <span>Unirse</span>
</button>
<!-- Aviso flotante para cuando no hay conexión con el servidor -->
<div id="aviso" class="aviso-flotante oculto" role="status" aria-live="polite">
  <span id="aviso-texto"></span>
</div>

<div id="qr-modal" class="qr-modal oculto" role="dialog" aria-label="QR para unirse">
  <div class="qr-grande">
    <p class="qr-titulo">Escaneá para jugar</p>
    <img class="qr" src="{{ $qrJugar }}" alt="Código QR para entrar a jugar">
    <p class="url-jugar">{{ $urlJugar }}</p>
    <p class="vacio">Tocá en cualquier lado para cerrar</p>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Formas tipo Kahoot para cada opción (rojo, azul, amarillo, verde)
    const FORMAS = ['▲', '◆', '●', '■'];
    const MEDALLAS = ['🥇', '🥈', '🥉'];
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const RUTAS = {
      estado: "{{ route('trivia.estado') }}",
      empezar: "{{ route('trivia.empezar') }}",
      pregunta: "{{ route('trivia.pregunta') }}",
      preparar: "{{ route('trivia.preparar') }}",
      generarLote: "{{ route('trivia.generar-lote') }}",
      revelar: "{{ route('trivia.revelar') }}",
      reiniciar: "{{ route('trivia.reiniciar') }}",
      quitar: "{{ route('trivia.quitar') }}",
    };

    // Atajo para buscar elementos por id
    const $ = (id) => document.getElementById(id);

    // ================================================================
    // ESTADO DEL HOST
    // ================================================================
    // "fase" dice en qué parte del ciclo estamos:
    //   config -> lanzando -> pregunta -> revelando -> resultado -> lanzando ... -> fin
    let fase = 'config';
    let config = { total: 10, segundos: 20, resultado: 8 };
    let pausado = false;

    // El "reloj" del juego: un único paso pendiente (qué hacer y cuándo)
    let paso = null;          // función que se ejecuta cuando vence el tiempo
    let pasoVenceEn = 0;      // Date.now() en que vence
    let restanteAlPausar = 0; // ms que faltaban cuando se pausó
    let timer = null;

    let numeroActual = null;   // pregunta que se está mostrando
    let numeroRevelado = null; // última pregunta revelada
    let deadlineLocal = 0;     // hora (en el reloj de ESTA compu) en que cierra la pregunta
    let segundosPregunta = 20;
    let prefetch = null;       // promesa del POST /trivia/preparar en curso
    let ultimoRanking = [];
    let jugadores = [];        // nombres anotados en la partida
    let conectados = new Set();   // nombres (en minúsculas) presentes en el canal de presencia
    let presenciaLista = false;   // false hasta que el canal de presencia responde (o si falla)
    let confirmandoQuitar = null; // jugador que tiene abierto el "¿Quitar a ...?"
    let nombresRespondieron = [];
    let generandoLote = false; // true mientras la IA genera un lote para el banco
    let bancoDisponibles = 0;  // preguntas sin usar en el banco

    // ================================================================
    // AYUDANTES
    // ================================================================

    // POST con fetch: token CSRF + pedimos JSON. Si hay error, lanza una
    // excepción con el mensaje del servidor y el código HTTP en e.status.
    async function post(url, datos = {}) {
      const respuesta = await pedir(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify(datos),
      });
      const json = await respuesta.json().catch(() => ({}));
      if (!respuesta.ok) throw errorDe(respuesta.status, json.message);
      return json;
    }

    // fetch() que avisa si no hay conexión. Cuando el pedido ni siquiera llega al servidor
    // (sin internet, servidor apagado) fetch lanza una excepción: mostramos el aviso
    // flotante y lanzamos el error con ese texto en vez del "Failed to fetch" del navegador.
    // No lleva .status, igual que antes: el juego reacciona como ante cualquier falla de red.
    const SIN_CONEXION = 'No hay conexión con el servidor. Revisá tu internet o probá en un rato.';
    let timerAviso = null;

    async function pedir(url, opciones) {
      try {
        return await fetch(url, opciones);
      } catch (e) {
        mostrarAviso(SIN_CONEXION);
        throw new Error(SIN_CONEXION);
      }
    }

    // Aviso flotante abajo (el mismo de /jugar): se va solo a los 8 segundos
    function mostrarAviso(texto) {
      $('aviso-texto').textContent = texto;
      $('aviso').classList.remove('oculto');
      clearTimeout(timerAviso);
      timerAviso = setTimeout(() => $('aviso').classList.add('oculto'), 8000);
    }

    // Textos amigables para los problemas del servidor que no son del juego. Sin esto el
    // cartel de error mostraría "CSRF token mismatch." o "Too Many Attempts.".
    const AVISOS = {
      419: 'Tu sesión venció por estar mucho rato abierta. Recargá la página (F5) y volvé a ingresar el PIN si te lo pide.',
      429: 'Demasiados pedidos seguidos. Esperá un minuto y probá de nuevo.',
      503: 'Estamos actualizando la trivia. Probá de nuevo en unos segundos.',
    };

    // Arma el error de un pedido que falló: mensaje para mostrar + código HTTP en .status.
    // El juego también responde 503 con mensajes propios ("El servidor está ocupado...",
    // "¿Está corriendo reverb:start?"): esos se respetan; solo se reemplaza el genérico
    // del modo mantenimiento.
    function errorDe(status, mensajeServidor) {
      const propioDelJuego = status === 503 && mensajeServidor && mensajeServidor !== 'Service Unavailable';
      const amigable = propioDelJuego ? null : AVISOS[status];
      const error = new Error(amigable || mensajeServidor || 'Error ' + status);
      error.status = status;
      return error;
    }

    // GET /trivia/estado. Si el servidor responde con error, lanza el mismo tipo de error que post()
    async function pedirEstado() {
      const respuesta = await pedir(RUTAS.estado, { headers: { 'Accept': 'application/json' } });
      const json = await respuesta.json().catch(() => ({}));
      if (!respuesta.ok) throw errorDe(respuesta.status, json.message);
      return json;
    }

    const esperar = (ms) => new Promise((ok) => setTimeout(ok, ms));

    function mostrarError(mensaje) {
      $('error').textContent = mensaje;
      $('error').classList.toggle('oculto', !mensaje);
    }

    function mostrarInfo(mensaje) {
      $('info').textContent = mensaje;
      $('info').classList.toggle('oculto', !mensaje);
    }

    function mostrarSeccion(cual) {
      $('seccion-config').classList.toggle('oculto', cual !== 'config');
      $('seccion-juego').classList.toggle('oculto', cual !== 'juego');
      $('seccion-podio').classList.toggle('oculto', cual !== 'podio');
      // Durante la partida, QR chico en la esquina para los que llegan tarde
      $('qr-mini').classList.toggle('oculto', cual !== 'juego');
      if (cual !== 'juego') $('qr-modal').classList.add('oculto');
    }

    // Tocar el QR chico lo agranda en un modal; tocar el modal lo cierra
    $('qr-mini').addEventListener('click', () => $('qr-modal').classList.remove('oculto'));
    $('qr-modal').addEventListener('click', () => $('qr-modal').classList.add('oculto'));

    // Habilita o deshabilita los botones según la fase
    function actualizarBotones() {
      const enJuego = !['config', 'fin'].includes(fase);
      $('btn-pausa').disabled = !enJuego;
      $('btn-pausa').textContent = pausado ? 'Reanudar' : 'Pausar';
      $('btn-saltar').disabled = fase !== 'pregunta';
      $('btn-empezar').disabled = fase !== 'config' || generandoLote;
      $('btn-generar-lote').disabled = fase !== 'config' || generandoLote;
    }

    // ================================================================
    // EL RELOJ: programar el próximo paso, pausar y reanudar
    // ================================================================

    function programar(funcion, ms) {
      clearTimeout(timer);
      paso = funcion;
      pasoVenceEn = Date.now() + Math.max(0, ms);
      if (pausado) {
        restanteAlPausar = Math.max(0, ms); // se va a usar al reanudar
      } else {
        timer = setTimeout(ejecutarPaso, Math.max(0, ms));
      }
    }

    function ejecutarPaso() {
      const funcion = paso;
      paso = null;
      if (funcion) funcion();
    }

    function cancelarPaso() {
      clearTimeout(timer);
      paso = null;
    }

    function pausar() {
      pausado = true;
      clearTimeout(timer);
      restanteAlPausar = Math.max(0, pasoVenceEn - Date.now());
      mostrarInfo('⏸ Juego en pausa');
      actualizarBotones();
    }

    function reanudar() {
      pausado = false;
      mostrarInfo('');
      mostrarError('');
      // Durante una pregunta el tiempo lo maneja el servidor (no se detiene),
      // así que revelamos cuando cierre; en las otras fases seguimos donde quedó.
      const ms = fase === 'pregunta' ? deadlineLocal + 700 - Date.now() : restanteAlPausar;
      if (paso) programar(paso, ms);
      actualizarBotones();
    }

    // Pausa el juego dejando anotado qué hacer al reanudar
    function pausarCon(funcion, mensajeError) {
      clearTimeout(timer);
      paso = funcion;
      pasoVenceEn = Date.now();
      pausar();
      if (mensajeError) mostrarError(mensajeError);
    }

    // ================================================================
    // EL CICLO AUTOMÁTICO
    // ================================================================

    // 1) Lanzar la siguiente pregunta (reintenta una vez si la IA falla)
    async function lanzar() {
      fase = 'lanzando';
      cancelarPaso();
      actualizarBotones();

      // Si el prefetch todavía está trabajando, lo esperamos (así no pedimos dos veces a la IA)
      if (prefetch) {
        mostrarInfo('Terminando de preparar la pregunta...');
        await prefetch;
      }
      mostrarInfo('Generando pregunta con IA...');

      let p = null;
      for (let intento = 1; intento <= 2 && !p; intento++) {
        try {
          p = await post(RUTAS.pregunta);
        } catch (e) {
          // Los 422 no son de la IA (por ej. la partida terminó): no tiene sentido reintentar
          if (intento === 1 && e.status !== 422) {
            mostrarError(e.message + ' Reintentando...');
            await esperar(3000);
          } else {
            mostrarInfo('');
            pausarCon(lanzar, e.message + ' El juego quedó en pausa: tocá "Reanudar" para reintentar.');
            return;
          }
        }
      }

      mostrarError('');
      mostrarInfo('');
      mostrarPregunta(p);
    }

    // 2) Mostrar la pregunta y programar el revelado para cuando cierre
    function mostrarPregunta(p) {
      // La misma pregunta llega por fetch y por Echo: si ya está en pantalla, no la redibujamos
      if (p.numero === numeroActual) return;
      numeroActual = p.numero;
      fase = 'pregunta';
      mostrarSeccion('juego');

      $('numero').textContent = 'Pregunta ' + p.numero + ' de ' + p.total;
      $('tema').textContent = p.tema ? 'Tema: ' + p.tema : '';
      $('tema').classList.toggle('oculto', !p.tema);
      $('texto').textContent = p.texto;

      $('opciones').innerHTML = '';
      p.opciones.forEach((op, i) => {
        const div = document.createElement('div');
        div.className = 'opcion op-' + i;
        div.innerHTML = '<span class="forma">' + FORMAS[i] + '</span><span></span>';
        div.lastChild.textContent = op; // textContent evita inyectar HTML
        $('opciones').appendChild(div);
      });

      nombresRespondieron = [];
      dibujarRespondieron();
      $('explicacion').classList.add('oculto');

      // Convertimos la hora de cierre del servidor a la hora de esta compu
      deadlineLocal = Date.now() + (p.cierraEn - p.ahora);
      segundosPregunta = p.segundos;
      $('reloj').classList.remove('oculto');

      // Revelamos un poquito después del cierre (el servidor acepta 0,5 s de margen)
      programar(revelar, deadlineLocal + 700 - Date.now());
      actualizarBotones();
    }

    // Llega una respuesta: si ya respondieron todos los conectados, revelamos antes
    function agregarRespuesta(nombre) {
      if (!jugadores.includes(nombre)) jugadores.push(nombre); // por si nos perdimos su JugadorUnido
      if (!nombresRespondieron.includes(nombre)) nombresRespondieron.push(nombre);
      dibujarRespondieron();
      dibujarJugadores();
      revisarSiRespondieronTodos(800);
    }

    // Chips de "quiénes respondieron" y el contador "3 de 5 conectados respondieron"
    function dibujarRespondieron() {
      $('respondieron').innerHTML = '';
      nombresRespondieron.forEach((nombre) => {
        const li = document.createElement('li');
        li.textContent = nombre; // textContent evita inyectar HTML
        $('respondieron').appendChild(li);
      });
      actualizarContador();
    }

    // El total son los conectados, más los que respondieron y después se desconectaron
    function actualizarContador() {
      const esperados = jugadores.filter((n) => estaConectado(n) || nombresRespondieron.includes(n));
      $('contador').textContent = nombresRespondieron.length;
      $('total-jugadores').textContent = esperados.length;
    }

    // "Todos" son los jugadores CONECTADOS: a uno que cerró la pestaña o se quedó sin
    // señal no lo esperamos (igual puede responder si vuelve antes de que cierre).
    function todosRespondieron() {
      const enLinea = jugadores.filter(estaConectado);
      return enLinea.length > 0 && enLinea.every((n) => nombresRespondieron.includes(n));
    }

    // Si ya respondieron todos los conectados, programa el revelado anticipado.
    // Se llama cuando llega una respuesta y cuando alguien se desconecta o es quitado.
    function revisarSiRespondieronTodos(ms) {
      if (fase !== 'pregunta' || !todosRespondieron()) return;
      mostrarInfo('¡Respondieron todos los conectados!');
      programar(revelarSiSiguenTodos, ms);
    }

    // Vuelve a chequear justo antes de revelar: si en el medio se conectó alguien que
    // todavía no respondió (por ej. recargó la página), se sigue esperando hasta el cierre.
    function revelarSiSiguenTodos() {
      if (fase !== 'pregunta') return;
      if (todosRespondieron()) {
        revelar();
      } else {
        mostrarInfo('');
        programar(revelar, deadlineLocal + 700 - Date.now());
      }
    }

    // 3) Revelar la respuesta correcta
    async function revelar() {
      if (fase !== 'pregunta') return;
      fase = 'revelando';
      cancelarPaso();
      actualizarBotones();

      try {
        const r = await post(RUTAS.revelar);
        mostrarResultado(r);
      } catch (e) {
        if (e.status === 422) {
          // Ya estaba cerrada (por ejemplo, la reveló otra pestaña): seguimos con la próxima
          fase = 'resultado';
          pausarCon(lanzar, e.message);
        } else {
          fase = 'pregunta';
          pausarCon(revelar, e.message + ' Tocá "Reanudar" para reintentar.');
        }
      }
    }

    // 4) Mostrar el resultado, preparar la siguiente y programar el próximo paso
    function mostrarResultado(r) {
      if (r.numero === numeroRevelado) return; // ya lo mostramos (llegó por fetch y por Echo)
      numeroRevelado = r.numero;
      fase = 'resultado';
      $('reloj').classList.add('oculto');
      $('barra-tiempo').style.width = '0';

      [...$('opciones').children].forEach((div, i) => {
        div.classList.toggle('correcta', i === r.correcta);
        div.classList.toggle('apagada', i !== r.correcta);
      });
      $('explicacion').textContent = r.explicacion;
      $('explicacion').classList.toggle('oculto', !r.explicacion);
      ultimoRanking = r.ranking;
      dibujarRanking($('ranking'), r.ranking, true);

      if (r.final) {
        programar(mostrarPodio, config.resultado * 1000);
      } else {
        // Prefetch: mientras todos miran el resultado, la IA ya prepara la próxima
        prefetch = post(RUTAS.preparar)
          .catch((e) => mostrarError('No se pudo preparar la siguiente pregunta (' + e.message + '). Se va a generar al lanzarla.'))
          .finally(() => { prefetch = null; });
        programar(lanzar, config.resultado * 1000);
      }
      actualizarBotones();
    }

    // 5) Fin: podio con el top 3
    function mostrarPodio() {
      fase = 'fin';
      cancelarPaso();
      pausado = false;
      mostrarInfo('');
      mostrarSeccion('podio');

      const podio = $('podio');
      podio.innerHTML = '';
      // Orden visual clásico: 2° - 1° - 3° (posiciones en la lista).
      // La medalla y la altura dependen del PUESTO que manda el servidor: si dos empatan
      // en el primer lugar, los dos llevan 🥇 y el escalón más alto.
      [1, 0, 2].forEach((i) => {
        const jugador = ultimoRanking[i];
        if (!jugador) return;
        const div = document.createElement('div');
        div.className = 'escalon esc-' + jugador.puesto;
        div.innerHTML = '<div class="medalla"></div><div class="quien"></div><div class="pts"></div><div class="bloque"></div>';
        div.children[0].textContent = MEDALLAS[jugador.puesto - 1];
        div.children[1].textContent = jugador.nombre;
        div.children[2].textContent = jugador.puntos + ' pts';
        div.children[3].textContent = jugador.puesto;
        podio.appendChild(div);
      });
      dibujarRanking($('ranking-final'), ultimoRanking, false);
      actualizarBotones();
    }

    function dibujarRanking(lista, ranking, conGanados) {
      lista.innerHTML = '';
      ranking.forEach((jugador, i) => {
        const li = document.createElement('li');
        li.className = 'puesto-' + jugador.puesto;
        li.innerHTML = '<span></span><span><span></span><span class="ganados"></span></span>';
        // Puesto calculado en el servidor (mismo puntaje = mismo puesto), igual que en /jugar
        const puesto = jugador.puesto <= 3 ? MEDALLAS[jugador.puesto - 1] : jugador.puesto + '°';
        li.firstChild.textContent = puesto + ' ' + jugador.nombre;
        li.lastChild.firstChild.textContent = jugador.puntos + ' pts';
        if (conGanados && jugador.ganados > 0) li.lastChild.lastChild.textContent = '+' + jugador.ganados;
        lista.appendChild(li);
      });
      if (lista === $('ranking')) $('ranking-vacio').classList.toggle('oculto', ranking.length > 0);
    }

    // Cada 250 ms actualizamos la cuenta regresiva y el texto "siguiente en..."
    setInterval(() => {
      if (fase === 'pregunta') {
        const restante = Math.max(0, deadlineLocal - Date.now());
        $('reloj').textContent = Math.ceil(restante / 1000);
        $('reloj').classList.toggle('urgente', restante <= 5000);
        $('barra-tiempo').style.width = (restante / (segundosPregunta * 1000) * 100) + '%';
      } else if (fase === 'resultado' && paso && !pausado) {
        const s = Math.ceil(Math.max(0, pasoVenceEn - Date.now()) / 1000);
        mostrarInfo(paso === mostrarPodio ? 'Podio en ' + s + ' s...' : 'Siguiente pregunta en ' + s + ' s...');
      }
    }, 250);

    // ================================================================
    // SALA DE ESPERA
    // ================================================================

    function agregarJugador(nombre) {
      if (!jugadores.includes(nombre)) jugadores.push(nombre);
      dibujarJugadores();
    }

    function limpiarSala() {
      jugadores = [];
      confirmandoQuitar = null;
      dibujarJugadores();
    }

    // Un jugador salió o fue quitado: desaparece de la sala y de "quiénes respondieron"
    function sacarJugador(nombre) {
      jugadores = jugadores.filter((n) => n !== nombre);
      nombresRespondieron = nombresRespondieron.filter((n) => n !== nombre);
      if (confirmandoQuitar === nombre) confirmandoQuitar = null;
      dibujarRespondieron();
      dibujarJugadores();
      // Si era el único que faltaba responder, ya están todos
      revisarSiRespondieronTodos(800);
    }

    // ================================================================
    // PRESENCIA: quién está conectado ahora (canal de presencia de Reverb)
    // ================================================================

    // Mientras el canal de presencia no responde (o si falla) no sabemos quién está
    // conectado: en ese caso contamos a todos, como antes de que existiera la presencia.
    function estaConectado(nombre) {
      return !presenciaLista || conectados.has(nombre.toLowerCase());
    }

    // Dibuja la lista de jugadores en la sala de espera y en la pantalla de juego
    function dibujarJugadores() {
      const enLinea = jugadores.filter(estaConectado).length;

      document.querySelectorAll('.js-cantidad').forEach((el) => { el.textContent = jugadores.length; });
      document.querySelectorAll('.js-conectados').forEach((el) => {
        el.textContent = presenciaLista ? '🟢 ' + enLinea + (enLinea === 1 ? ' conectado' : ' conectados') : '';
      });
      document.querySelectorAll('.js-lista-jugadores').forEach((lista) => {
        lista.innerHTML = '';
        jugadores.forEach((nombre) => lista.appendChild(crearChipJugador(nombre)));
      });
      actualizarContador();
    }

    function crearBoton(clase, texto, alTocar) {
      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = clase;
      boton.textContent = texto;
      boton.addEventListener('click', alTocar);
      return boton;
    }

    // Un jugador de la lista: "🟢 Ana ✕" o, al tocar la ✕, "¿Quitar a Ana? [Sí, quitar] [No]"
    function crearChipJugador(nombre) {
      const li = document.createElement('li');
      const conectado = estaConectado(nombre);
      li.className = 'jugador' + (conectado ? '' : ' desconectado');

      const texto = document.createElement('span');
      texto.className = 'nombre';

      if (confirmandoQuitar === nombre) {
        // Confirmación dentro de la página (sin el confirm() del navegador)
        li.classList.add('confirmando');
        texto.textContent = '¿Quitar a ' + nombre + '?'; // textContent evita inyectar HTML
        li.appendChild(texto);
        li.appendChild(crearBoton('btn-chip peligro', 'Sí, quitar', () => quitarJugador(nombre)));
        li.appendChild(crearBoton('btn-chip', 'No', () => {
          confirmandoQuitar = null;
          dibujarJugadores();
        }));
        return li;
      }

      if (presenciaLista) {
        const estado = document.createElement('span');
        estado.className = 'estado';
        estado.textContent = conectado ? '🟢' : '⚪';
        estado.title = conectado ? 'Conectado' : 'Desconectado';
        li.appendChild(estado);
      }

      texto.textContent = nombre;
      li.appendChild(texto);

      const quitar = crearBoton('btn-quitar', '✕', () => {
        confirmandoQuitar = nombre;
        dibujarJugadores();
      });
      quitar.title = 'Quitar a ' + nombre + ' de la partida';
      quitar.setAttribute('aria-label', quitar.title);
      li.appendChild(quitar);
      return li;
    }

    // El host quita a un jugador: el servidor lo borra y avisa a todos con JugadorSalio
    async function quitarJugador(nombre) {
      confirmandoQuitar = null;
      mostrarError('');
      try {
        await post(RUTAS.quitar, { nombre: nombre });
        sacarJugador(nombre);
      } catch (e) {
        if (e.status === 422) {
          sacarJugador(nombre); // ya no estaba (por ejemplo, justo salió solo)
        } else {
          mostrarError(e.message);
          dibujarJugadores();
        }
      }
    }

    // Echo.join() entra al canal de presencia (el servidor nos autoriza por la sesión con PIN):
    //  - here:    lista completa de los que ya estaban, al entrar (y al reconectar)
    //  - joining: alguien se conectó
    //  - leaving: alguien cerró la pestaña o se quedó sin conexión
    // El host también es un miembro del canal (host: true): no lo contamos como jugador.
    const claveDe = (miembro) => String(miembro.nombre).toLowerCase();

    window.Echo.join('trivia.presencia')
      .here((miembros) => {
        conectados = new Set(miembros.filter((m) => !m.host).map(claveDe));
        presenciaLista = true;
        dibujarJugadores();
        revisarSiRespondieronTodos(3000);
      })
      .joining((miembro) => {
        if (miembro.host) return;
        conectados.add(claveDe(miembro));
        dibujarJugadores();
      })
      .leaving((miembro) => {
        if (miembro.host) return;
        conectados.delete(claveDe(miembro));
        dibujarJugadores();
        // Damos 3 s antes de revelar: si solo recargó la página, vuelve a aparecer enseguida
        revisarSiRespondieronTodos(3000);
      })
      .error(() => {
        // No se pudo entrar al canal: seguimos sin datos de conexión (se cuenta a todos)
        presenciaLista = false;
        dibujarJugadores();
      });

    // Vuelve a la pantalla de configuración (después de reiniciar o terminar)
    function volverAConfig() {
      cancelarPaso();
      pausado = false;
      fase = 'config';
      numeroActual = null;
      numeroRevelado = null;
      mostrarInfo('');
      mostrarSeccion('config');
      actualizarBotones();
      refrescarBanco(); // durante la partida se usaron preguntas del banco
    }

    // ================================================================
    // BANCO DE PREGUNTAS ("Banco: N preguntas disponibles")
    // ================================================================

    // Muestra cuántas hay; en rojo si no alcanzan para la cantidad de preguntas configurada
    function mostrarBanco(n) {
      bancoDisponibles = n;
      $('banco-n').textContent = n;
      const necesarias = parseInt($('cfg-total').value, 10) || 0;
      $('banco').classList.toggle('insuficiente', n < necesarias);
    }

    function refrescarBanco() {
      pedirEstado()
        .then((estado) => mostrarBanco(estado.banco))
        .catch((e) => {
          // Es solo un refresco: una falla de red se ignora, pero un 419/429/503 se avisa
          if (AVISOS[e.status]) mostrarError(e.message);
        });
    }

    // "Generar 10 más": UN pedido a la IA que trae 10 preguntas y las guarda en el banco
    async function generarLote() {
      mostrarError('');
      generandoLote = true;
      actualizarBotones();
      mostrarInfo('Generando 10 preguntas con IA (puede tardar hasta un minuto)...');

      try {
        const r = await post(RUTAS.generarLote);
        mostrarBanco(r.banco);
        mostrarInfo('Se agregaron ' + r.guardadas + ' preguntas al banco.');
      } catch (e) {
        mostrarInfo('');
        mostrarError(e.message);
      } finally {
        generandoLote = false;
        actualizarBotones();
      }
    }

    // Si cambia la cantidad de preguntas de la partida, se recalcula el color del banco
    $('cfg-total').addEventListener('input', () => mostrarBanco(bancoDisponibles));

    // ================================================================
    // BOTONES
    // ================================================================

    $('btn-empezar').addEventListener('click', async () => {
      mostrarError('');
      const datos = {
        total: parseInt($('cfg-total').value, 10),
        segundos: parseInt($('cfg-segundos').value, 10),
        resultado: parseInt($('cfg-resultado').value, 10),
      };
      $('btn-empezar').disabled = true;
      try {
        const r = await post(RUTAS.empezar, datos);
        config = r.config;
        numeroActual = null;
        numeroRevelado = null;
        dibujarRanking($('ranking'), [], false);
        $('opciones').innerHTML = '';
        $('numero').textContent = 'Pregunta 1 de ' + config.total;
        $('texto').textContent = 'Preparando la primera pregunta...';
        mostrarSeccion('juego');
        lanzar();
      } catch (e) {
        mostrarError(e.message);
        $('btn-empezar').disabled = false;
      }
    });

    $('btn-generar-lote').addEventListener('click', () => generarLote());

    $('btn-pausa').addEventListener('click', () => {
      if (pausado) reanudar(); else pausar();
    });

    $('btn-saltar').addEventListener('click', () => revelar());

    $('btn-nueva-partida').addEventListener('click', () => volverAConfig());

    $('btn-reiniciar').addEventListener('click', async () => {
      if (!confirm('¿Reiniciar la partida? Se borran puntajes, jugadores y preguntas hechas.')) return;
      mostrarError('');
      cancelarPaso();
      try {
        await post(RUTAS.reiniciar);
        limpiarSala();
        volverAConfig();
      } catch (e) {
        mostrarError(e.message);
      }
    });

    // ================================================================
    // EVENTOS EN VIVO (Reverb + Echo)
    // El punto delante del nombre indica que usamos el nombre de broadcastAs()
    // ================================================================

    window.Echo.channel('trivia')
      .listen('.JugadorUnido', (e) => agregarJugador(e.nombre))
      .listen('.JugadorSalio', (e) => {
        sacarJugador(e.nombre);
        // El ranking llega ya sin ese jugador (y con los puestos recalculados)
        ultimoRanking = e.ranking;
        if (fase === 'fin') {
          mostrarPodio();
        } else {
          dibujarRanking($('ranking'), e.ranking, fase === 'resultado');
        }
      })
      .listen('.PreguntaLanzada', (e) => {
        if (fase === 'lanzando') mostrarPregunta(e);
      })
      .listen('.RespuestaRecibida', (e) => agregarRespuesta(e.nombre))
      .listen('.ResultadoRevelado', (e) => {
        if (fase === 'revelando' || fase === 'pregunta') mostrarResultado(e);
      })
      .listen('.PartidaReiniciada', () => {
        limpiarSala();
        volverAConfig();
      });

    // ================================================================
    // AL CARGAR: recuperar el estado (por si el host recargó la página)
    // ================================================================

    pedirEstado()
      .then((estado) => {
        estado.jugadores.forEach((n) => agregarJugador(n));

        if (estado.config) {
          config = estado.config;
          $('cfg-total').value = config.total;
          $('cfg-segundos').value = config.segundos;
          $('cfg-resultado').value = config.resultado;
        }
        mostrarBanco(estado.banco); // después de cargar la config, para comparar con la cantidad
        ultimoRanking = estado.ranking;
        dibujarRanking($('ranking'), estado.ranking, false);

        if (estado.pregunta) {
          // Había una pregunta abierta: la mostramos y seguimos el ciclo
          mostrarPregunta({
            numero: estado.numero, total: estado.total,
            texto: estado.pregunta.texto, opciones: estado.pregunta.opciones, tema: estado.pregunta.tema,
            segundos: estado.segundos, cierraEn: estado.cierraEn, ahora: estado.ahora,
          });
          estado.respondieron.forEach((n) => agregarRespuesta(n));
        } else if (estado.terminada) {
          mostrarPodio();
        } else if (estado.numero > 0) {
          // Partida a mitad de camino, entre preguntas
          mostrarSeccion('juego');
          $('numero').textContent = 'Pregunta ' + estado.numero + ' de ' + estado.total;
          $('texto').textContent = 'Partida en curso';
          numeroRevelado = estado.numero;
          fase = 'resultado';
          pausarCon(lanzar);
          mostrarInfo('Partida en curso: tocá "Reanudar" para lanzar la siguiente pregunta.');
        }
        actualizarBotones();
      })
      // Con 419/429/503 se muestra el aviso amigable; con cualquier otra falla, el genérico
      .catch((e) => mostrarError(AVISOS[e.status] ? e.message : 'No se pudo cargar el estado de la partida.'));
  });
</script>
</body>
</html>
