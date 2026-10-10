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
      color: var(--suave); text-decoration: underline; cursor: pointer; text-align: right;
    }
    .link:disabled { opacity: .5; }
    /* "Salir de la partida" pide confirmar con un segundo toque */
    .link.confirmar { color: var(--error); font-weight: 800; }

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
      position: relative; overflow: hidden;
      background: var(--panel); border: 1px solid var(--borde);
      border-radius: 16px; padding: 18px 18px 24px; text-align: center;
    }
    .pregunta .cabecera { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .pregunta .numero { color: var(--suave); font-size: 14px; font-weight: 700; margin: 0; }
    .reloj {
      min-width: 44px; height: 44px; border-radius: 22px; padding: 0 10px;
      display: inline-flex; align-items: center; justify-content: center;
      background: #6c5ce7; font-size: 20px; font-weight: 800;
    }
    .reloj.urgente { background: var(--rojo); }
    .pregunta .tema { color: var(--amarillo); font-size: 12px; font-weight: 600; line-height: 1.35; margin: 0 0 8px; }
    .pregunta h2 { font-size: 20px; font-weight: 800; line-height: 1.3; margin: 0; }
    .barra-tiempo { position: absolute; left: 0; bottom: 0; height: 6px; background: var(--amarillo); transition: width .2s linear; }

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

    /* Pantalla de resultado: el orden cambia si terminó la partida (con "order" de flexbox) */
    #pantalla-resultado { justify-content: flex-start; gap: 14px; padding-bottom: 8px; }
    #final { order: 1; }
    #ronda { order: 2; }
    #tarjeta-ranking { order: 3; }
    #texto-siguiente { order: 4; }
    #pantalla-resultado.es-final #tarjeta-ranking { order: 2; }
    #pantalla-resultado.es-final #ronda { order: 3; }

    /* Resultado de la pregunta (con su explicación). Al final de la partida se vuelve plegable. */
    .ronda {
      border-radius: 20px; padding: 20px 18px; text-align: center;
      background: var(--panel); border: 2px solid var(--borde);
    }
    .ronda.bien { border-color: var(--ok); background: rgba(61, 220, 132, .12); }
    .ronda.mal { border-color: var(--error); background: rgba(255, 92, 116, .12); }
    .ronda summary { display: none; } /* durante la partida no se puede plegar */
    .ronda h2 { font-size: 28px; font-weight: 800; margin: 0 0 6px; }
    .ronda .era { font-size: 18px; font-weight: 600; margin: 0 0 10px; }
    .explicacion { color: #d6d8ff; font-size: 15px; line-height: 1.5; margin: 0; }
    /* Versión compacta y plegable al terminar */
    .es-final .ronda { padding: 12px 16px; border-width: 1px; text-align: left; }
    .es-final .ronda summary {
      display: block; cursor: pointer; font-weight: 700; font-size: 15px; color: var(--suave);
      list-style: none;
    }
    .es-final .ronda summary::-webkit-details-marker { display: none; }
    .es-final .ronda summary::before { content: '▸ '; }
    .es-final .ronda[open] summary::before { content: '▾ '; }
    .es-final .ronda[open] summary { margin-bottom: 10px; }
    .es-final .ronda h2 { font-size: 18px; }
    .es-final .ronda .era { font-size: 15px; }

    /* Tarjeta del ranking */
    .tarjeta-ranking {
      background: var(--panel); border: 1px solid var(--borde);
      border-radius: 20px; padding: 16px 12px 12px;
    }
    .resumen { font-size: 17px; font-weight: 700; margin: 0 4px 12px; line-height: 1.45; text-align: center; }
    .resumen .mas { color: var(--ok); }

    .lista-ranking { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
    .fila {
      display: grid; grid-template-columns: 34px 1fr auto; align-items: center; gap: 8px;
      min-height: 40px; padding: 6px 10px; border-radius: 10px;
      background: #24274d; border: 2px solid transparent;
      font-size: 15px; font-weight: 600;
    }
    .fila .puesto { text-align: center; font-weight: 800; color: var(--suave); }
    .fila .puesto.medalla { font-size: 20px; }
    .fila .nombre { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fila .pts { text-align: right; font-weight: 800; white-space: nowrap; }
    .fila .pts small { display: block; font-size: 11px; font-weight: 700; color: var(--ok); line-height: 1; }
    /* Mi fila: siempre resaltada */
    .fila.yo { background: rgba(216, 158, 0, .18); border-color: var(--amarillo); }
    .fila.yo .puesto { color: var(--amarillo); }
    .vos {
      display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px;
      background: var(--amarillo); color: #1b1d36; font-size: 11px; font-weight: 800;
      font-style: normal; vertical-align: middle;
    }
    .separador { text-align: center; color: var(--suave); font-weight: 800; letter-spacing: 4px; line-height: 1; padding: 2px 0; }

    .btn-ranking {
      font: inherit; font-size: 15px; font-weight: 700; width: 100%; margin-top: 10px;
      padding: 10px; border-radius: 12px; cursor: pointer;
      background: transparent; color: var(--suave); border: 1px solid var(--borde);
    }
    /* Ranking completo: scroll dentro de la tarjeta */
    .lista-completa { position: relative; max-height: 55vh; overflow-y: auto; overscroll-behavior: contain; padding-right: 2px; }

    /* Mensaje final de la partida */
    .final {
      border-radius: 20px; padding: 22px 18px; text-align: center;
      background: linear-gradient(135deg, #3b2f8f, #1b1d36); border: 2px solid #6c5ce7;
    }
    .final .medalla { font-size: 64px; line-height: 1; }
    .final h2 { font-size: 26px; font-weight: 800; margin: 8px 0 4px; }
    .final p { margin: 0; color: #c9ccff; font-weight: 600; }

    @media (max-width: 380px) {
      .opcion { min-height: 100px; font-size: 14px; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <div id="barra" class="barra oculto">
    <span>Jugando como <strong id="mi-nombre"></strong> · <strong id="mis-puntos">0</strong> pts</span>
    <button id="btn-salir" class="link" type="button">Salir de la partida</button>
  </div>

  <!-- 1) Ingresar nombre -->
  <form id="pantalla-nombre" class="pantalla">
    <h1>Trivia <span>en vivo</span></h1>
    <p class="suave centro">Programación IV · UTN</p>
    <input id="input-nombre" type="text" maxlength="30" placeholder="Tu nombre" autocomplete="off" required>
    <p id="error-nombre" class="mensaje mensaje-error oculto"></p>
    <button id="btn-entrar" class="btn" type="submit">Entrar</button>
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
      <div class="cabecera">
        <p id="numero" class="numero"></p>
        <span id="reloj" class="reloj">0</span>
      </div>
      <p id="tema" class="tema oculto"></p>
      <h2 id="texto"></h2>
      <div id="barra-tiempo" class="barra-tiempo" style="width: 100%"></div>
    </div>
    <div id="opciones" class="opciones"></div>
    <p id="mensaje" class="mensaje oculto"></p>
  </section>

  <!-- 4) Resultado de la ronda (y fin de la partida) -->
  <section id="pantalla-resultado" class="pantalla oculto">
    <div id="final" class="final oculto">
      <div id="final-medalla" class="medalla"></div>
      <h2 id="final-titulo"></h2>
      <p id="final-detalle"></p>
    </div>
    <!-- Resultado de la pregunta. Es un <details> para poder plegarlo al final de la partida. -->
    <details id="ronda" class="ronda" open>
      <summary id="ronda-resumen"></summary>
      <h2 id="resultado-titulo"></h2>
      <p id="resultado-era" class="era"></p>
      <p id="explicacion" class="explicacion"></p>
    </details>

    <div id="tarjeta-ranking" class="tarjeta-ranking">
      <p id="resumen" class="resumen"></p>
      <ol id="ranking-corto" class="lista-ranking"></ol>
      <ol id="ranking-completo" class="lista-ranking lista-completa oculto"></ol>
      <button id="btn-ranking" class="btn-ranking" type="button">Ver ranking completo</button>
    </div>

    <p id="texto-siguiente" class="suave centro">Esperando la próxima pregunta...</p>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const FORMAS = ['▲', '◆', '●', '■'];
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const RUTAS = {
      estado: "{{ route('trivia.estado') }}",
      unirse: "{{ route('trivia.unirse') }}",
      responder: "{{ route('trivia.responder') }}",
      reconectar: "{{ route('trivia.reconectar') }}",
      salir: "{{ route('trivia.abandonar') }}",
    };
    const $ = (id) => document.getElementById(id);

    // ================================================================
    // DATOS GUARDADOS EN EL NAVEGADOR (localStorage)
    // A diferencia de sessionStorage, localStorage sobrevive a cerrar la pestaña:
    // así el jugador puede volver a entrar sin escribir el nombre de nuevo.
    // Va todo con try/catch porque algunos navegadores lo bloquean (modo privado,
    // cookies deshabilitadas): en ese caso se juega igual, solo que sin "recordar".
    // ================================================================
    const guardado = {
      leer(clave) {
        try { return localStorage.getItem(clave) || ''; } catch (e) { return ''; }
      },
      escribir(clave, valor) {
        try { localStorage.setItem(clave, valor); } catch (e) { /* se sigue sin guardar */ }
      },
      borrar(clave) {
        try { localStorage.removeItem(clave); } catch (e) { /* nada que borrar */ }
      },
    };

    // Estado del jugador en el navegador.
    // El token lo da el servidor al anotarse: prueba que este nombre es "mío".
    let nombre = guardado.leer('trivia.nombre');
    let token = guardado.leer('trivia.token');

    // Con esto se autoriza el canal de presencia (lo llama resources/js/echo.js al suscribirse)
    window.triviaCredenciales = () => (token ? { nombre: nombre, token: token } : {});
    let opcionesActuales = [];  // textos de las 4 opciones de la pregunta actual
    let miEleccion = null;      // índice (0-3) que tocó el jugador, o null si no respondió
    let numeroActual = null;    // número de la pregunta en pantalla
    let deadlineLocal = 0;      // hora (en el reloj de ESTE celular) en que cierra la pregunta
    let segundosPregunta = 20;

    const pantallas = ['nombre', 'espera', 'pregunta', 'resultado'];

    // Muestra solo una de las pantallas
    function mostrarPantalla(cual) {
      pantallas.forEach((p) => $('pantalla-' + p).classList.toggle('oculto', p !== cual));
      $('barra').classList.toggle('oculto', cual === 'nombre');
    }

    function mostrarMensaje(texto, tipo = 'info') {
      $('mensaje').textContent = texto;
      $('mensaje').className = 'mensaje mensaje-' + tipo;
      $('mensaje').classList.toggle('oculto', !texto);
    }

    // POST con fetch (token CSRF + JSON). Devuelve { ok, status, json }.
    async function post(url, datos) {
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
      return { ok: respuesta.ok, status: respuesta.status, json };
    }

    // ================================================================
    // NOMBRE: anotarse en el servidor (nombres únicos)
    // ================================================================

    // Vuelve a la pantalla de nombre mostrando un error
    function pedirNombre(error) {
      $('error-nombre').textContent = error || '';
      $('error-nombre').classList.toggle('oculto', !error);
      $('input-nombre').value = nombre;
      mostrarPantalla('nombre');
      $('input-nombre').focus();
    }

    // Se anota con el nombre (si ya estaba anotado con este token, el servidor lo acepta igual)
    async function unirse(nombreDeseado) {
      $('btn-entrar').disabled = true;
      try {
        const r = await post(RUTAS.unirse, { nombre: nombreDeseado, token: token });
        if (!r.ok) {
          pedirNombre(r.json.message || 'No se pudo entrar (error ' + r.status + ').');
          return false;
        }
        recordarJugador(r.json.nombre, r.json.token);
        return true;
      } catch (e) {
        pedirNombre('No hay conexión con el servidor. Probá de nuevo.');
        return false;
      } finally {
        $('btn-entrar').disabled = false;
      }
    }

    // Guarda el nombre (tal como lo registró el servidor) y el token, y avisa que estamos conectados
    function recordarJugador(nuevoNombre, nuevoToken) {
      nombre = nuevoNombre;
      token = nuevoToken;
      guardado.escribir('trivia.nombre', nombre);
      guardado.escribir('trivia.token', token);
      $('mi-nombre').textContent = nombre;
      conectarPresencia();
    }

    // Borra todo lo del jugador en este navegador (salió, lo quitaron o el token ya no vale)
    function olvidarJugador() {
      guardado.borrar('trivia.nombre');
      guardado.borrar('trivia.token');
      nombre = '';
      token = '';
      numeroActual = null;
      mostrarMisPuntos(0);
      window.Echo.leave('trivia.presencia');
    }

    function mostrarMisPuntos(puntos) {
      $('mis-puntos').textContent = puntos;
    }

    // Busca mis puntos en un ranking que mandó el servidor
    function mostrarMisPuntosDe(ranking) {
      const yo = nombre.toLowerCase();
      const mio = (ranking || []).find((j) => String(j.nombre).toLowerCase() === yo);
      if (mio) mostrarMisPuntos(mio.puntos);
    }

    // ================================================================
    // PRESENCIA: entrar al canal para que el host nos vea "conectados"
    // ================================================================

    // Echo.join() pide permiso a /broadcasting/auth mandando nombre y token (ver echo.js).
    // Mientras esta pestaña esté abierta y con conexión, el host nos ve en verde; al
    // cerrarla o perder la señal, Reverb avisa solo y pasamos a "desconectado".
    function conectarPresencia() {
      window.Echo.leave('trivia.presencia'); // por si ya estábamos (con otro nombre o token)
      window.Echo.join('trivia.presencia');
    }

    // ================================================================
    // RECONEXIÓN: volver a entrar con lo guardado, sin escribir el nombre
    // ================================================================

    // Pregunta al servidor si el nombre y el token guardados siguen valiendo en la partida actual
    async function reconectar() {
      try {
        const r = await post(RUTAS.reconectar, { nombre: nombre, token: token });

        if (r.ok) {
          recordarJugador(r.json.nombre, r.json.token);
          mostrarMisPuntos(r.json.puntos);
          await cargarEstado();
        } else if (r.status === 422) {
          // Ya no valen (por ejemplo, se reinició la partida): se borran y se pide el nombre
          olvidarJugador();
          pedirNombre('');
        } else {
          // Servidor ocupado o demasiados pedidos: no borramos nada, puede reintentar con "Entrar"
          pedirNombre('No se pudo volver a entrar (error ' + r.status + '). Tocá "Entrar" para reintentar.');
        }
      } catch (e) {
        pedirNombre('No hay conexión con el servidor. Tocá "Entrar" para reintentar.');
      }
    }

    // ================================================================
    // SALIR DE LA PARTIDA
    // ================================================================

    let confirmarSalida = null; // timer del "tocá de nuevo para confirmar"

    function restaurarBotonSalir() {
      clearTimeout(confirmarSalida);
      confirmarSalida = null;
      $('btn-salir').textContent = 'Salir de la partida';
      $('btn-salir').classList.remove('confirmar');
      $('btn-salir').disabled = false;
    }

    // Primer toque: pide confirmación (4 s). Segundo toque: sale de verdad.
    $('btn-salir').addEventListener('click', async () => {
      if (!confirmarSalida) {
        $('btn-salir').textContent = '¿Salir y perder tus puntos? Tocá de nuevo';
        $('btn-salir').classList.add('confirmar');
        confirmarSalida = setTimeout(restaurarBotonSalir, 4000);
        return;
      }

      clearTimeout(confirmarSalida);
      $('btn-salir').disabled = true;

      try {
        // El servidor borra al jugador: el nombre queda libre y sus puntos desaparecen
        const r = await post(RUTAS.salir, { nombre: nombre, token: token });

        // Con 422 el servidor ya no nos conocía: igual limpiamos este navegador
        if (r.ok || r.status === 422) {
          restaurarBotonSalir();
          olvidarJugador();
          pedirNombre('');
        } else {
          avisarQueNoSalio('No se pudo salir (error ' + r.status + '). Tocá para reintentar');
        }
      } catch (e) {
        avisarQueNoSalio('Sin conexión: no se pudo salir. Tocá para reintentar');
      }
    });

    // Si falla el pedido, el aviso va en el mismo botón: tocarlo de nuevo reintenta
    function avisarQueNoSalio(texto) {
      $('btn-salir').disabled = false;
      $('btn-salir').textContent = texto;
      confirmarSalida = setTimeout(restaurarBotonSalir, 4000);
    }

    // ================================================================
    // PREGUNTA
    // ================================================================

    function bloquearOpciones(elegida) {
      [...$('opciones').children].forEach((btn, i) => {
        btn.disabled = true;
        btn.classList.toggle('elegida', i === elegida);
      });
    }

    function desbloquearOpciones() {
      [...$('opciones').children].forEach((btn) => {
        btn.disabled = false;
        btn.classList.remove('elegida');
      });
    }

    // Dibuja la pregunta con 4 botones grandes de colores y arranca la cuenta regresiva
    function mostrarPregunta(p) {
      numeroActual = p.numero;
      opcionesActuales = p.opciones;
      miEleccion = null;
      $('numero').textContent = 'Pregunta ' + p.numero + ' de ' + p.total;
      $('tema').textContent = p.tema ? 'Tema: ' + p.tema : '';
      $('tema').classList.toggle('oculto', !p.tema);
      $('texto').textContent = p.texto;
      mostrarMensaje('');

      // El servidor manda su hora ("ahora") y la de cierre: la diferencia es lo que falta,
      // sin importar si el reloj del celular está adelantado o atrasado
      deadlineLocal = Date.now() + (p.cierraEn - p.ahora);
      segundosPregunta = p.segundos;

      $('opciones').innerHTML = '';
      p.opciones.forEach((op, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'opcion op-' + i;
        btn.innerHTML = '<span class="forma">' + FORMAS[i] + '</span><span></span>';
        btn.lastChild.textContent = op; // textContent evita inyectar HTML
        btn.addEventListener('click', () => responder(i));
        $('opciones').appendChild(btn);
      });

      actualizarReloj();
      mostrarPantalla('pregunta');
    }

    // Cuenta regresiva (se llama cada 200 ms)
    function actualizarReloj() {
      if (numeroActual === null) return;
      const restante = Math.max(0, deadlineLocal - Date.now());
      $('reloj').textContent = Math.ceil(restante / 1000);
      $('reloj').classList.toggle('urgente', restante <= 5000);
      $('barra-tiempo').style.width = (restante / (segundosPregunta * 1000) * 100) + '%';

      // Se acabó el tiempo y no respondió: bloqueamos los botones
      if (restante === 0 && miEleccion === null && !$('pantalla-pregunta').classList.contains('oculto')) {
        miEleccion = -1; // -1 = no llegó a responder
        bloquearOpciones(null);
        mostrarMensaje('⏱️ ¡Se acabó el tiempo!', 'error');
      }
    }
    setInterval(actualizarReloj, 200);

    // Manda la respuesta al servidor
    async function responder(i) {
      if (miEleccion !== null) return;
      miEleccion = i;            // la recordamos para compararla al revelar
      bloquearOpciones(i);
      mostrarMensaje('Respuesta enviada. ¡Esperá el resultado!');

      try {
        const r = await post(RUTAS.responder, { nombre: nombre, token: token, opcion: i });
        if (r.ok) return;

        mostrarMensaje(r.json.message || 'Error ' + r.status, 'error');

        if (r.json.reingresar) {
          // El servidor no nos reconoce (por ejemplo, el host nos quitó): lo guardado ya
          // no sirve. Dejamos el nombre escrito para que sea fácil volver a entrar.
          const nombreAnterior = nombre;
          olvidarJugador();
          pedirNombre(r.json.message);
          $('input-nombre').value = nombreAnterior;
        } else if (r.status !== 422) {
          // Error de red o servidor ocupado (no un 422): dejamos reintentar
          miEleccion = null;
          desbloquearOpciones();
        }
        // Con 422 (tiempo terminado, ya respondió...) los botones quedan bloqueados
      } catch (e) {
        mostrarMensaje('No se pudo enviar la respuesta. Revisá la conexión y tocá de nuevo.', 'error');
        miEleccion = null;
        desbloquearOpciones();
      }
    }

    // ================================================================
    // RESULTADO Y RANKING
    // ================================================================

    const MEDALLAS = ['🥇', '🥈', '🥉'];
    const TOP = 5;                 // filas que se muestran siempre
    let rankingAbierto = false;    // "Ver ranking completo" (arranca plegado y recuerda la elección)

    // Arma una fila del ranking: puesto (o medalla), nombre (+ "Vos") y puntos (+ ganados).
    // El puesto viene calculado del servidor (con empates: mismo puntaje = mismo puesto).
    function crearFila(jugador, esYo, conGanados) {
      const puesto = jugador.puesto;
      const li = document.createElement('li');
      li.className = 'fila' + (esYo ? ' yo' : '');
      li.innerHTML = '<span class="puesto"></span><span class="nombre"></span><span class="pts"></span>';

      const celdaPuesto = li.children[0];
      if (puesto <= 3) {
        celdaPuesto.textContent = MEDALLAS[puesto - 1];
        celdaPuesto.classList.add('medalla');
      } else {
        celdaPuesto.textContent = puesto + '°';
      }

      li.children[1].textContent = jugador.nombre; // textContent evita inyectar HTML
      if (esYo) {
        const vos = document.createElement('em');
        vos.className = 'vos';
        vos.textContent = 'Vos';
        li.children[1].appendChild(vos);
      }

      li.children[2].textContent = jugador.puntos;
      if (conGanados && jugador.ganados > 0) {
        const mas = document.createElement('small');
        mas.textContent = '+' + jugador.ganados;
        li.children[2].appendChild(mas);
      }
      return li;
    }

    function crearSeparador() {
      const li = document.createElement('li');
      li.className = 'separador';
      li.textContent = '···';
      return li;
    }

    // Dibuja el ranking corto (top 5 + mi zona) y el completo (plegado)
    function dibujarRanking(ranking, miPos, conGanados) {
      const corto = $('ranking-corto');
      const completo = $('ranking-completo');
      corto.innerHTML = '';
      completo.innerHTML = '';

      // Top 5 siempre
      ranking.slice(0, TOP).forEach((j, i) => corto.appendChild(crearFila(j, i === miPos, conGanados)));

      // Si no estoy en el top 5: "···" y mi fila con el de arriba y el de abajo
      if (miPos >= TOP) {
        const desde = Math.max(TOP, miPos - 1);
        const hasta = Math.min(ranking.length - 1, miPos + 1);
        if (desde > TOP) corto.appendChild(crearSeparador());
        for (let i = desde; i <= hasta; i++) {
          corto.appendChild(crearFila(ranking[i], i === miPos, conGanados));
        }
      }

      // Lista completa (con scroll dentro de la tarjeta)
      ranking.forEach((j, i) => completo.appendChild(crearFila(j, i === miPos, conGanados)));

      // El botón solo tiene sentido si hay más gente de la que ya se ve
      const hayMas = ranking.length > corto.querySelectorAll('.fila').length;
      $('btn-ranking').classList.toggle('oculto', !hayMas);
      aplicarRankingAbierto(hayMas && rankingAbierto);
    }

    // Muestra la lista completa o la corta y centra mi fila dentro del scroll
    function aplicarRankingAbierto(abierto) {
      $('ranking-corto').classList.toggle('oculto', abierto);
      $('ranking-completo').classList.toggle('oculto', !abierto);
      $('btn-ranking').textContent = abierto
        ? 'Ocultar ranking completo'
        : 'Ver ranking completo (' + $('ranking-completo').children.length + ')';

      const miFila = $('ranking-completo').querySelector('.fila.yo');
      if (abierto && miFila) {
        const lista = $('ranking-completo');
        lista.scrollTop = miFila.offsetTop - lista.clientHeight / 2 + miFila.clientHeight / 2;
      }
    }

    $('btn-ranking').addEventListener('click', () => {
      rankingAbierto = !rankingAbierto;
      aplicarRankingAbierto(rankingAbierto);
    });

    // Muestra el resultado de la pregunta y el ranking.
    // correcta === null significa "solo mostrar la posición" (por ej. al recargar con la partida terminada).
    function mostrarResultado(correcta, textoExplicacion, ranking, final) {
      numeroActual = null;
      mostrarMisPuntosDe(ranking);

      // Buscamos al jugador en el ranking (el puesto con empates ya viene del servidor)
      const yo = nombre.toLowerCase();
      const miPos = ranking.findIndex((j) => String(j.nombre).toLowerCase() === yo);
      const miPuesto = miPos >= 0 ? ranking[miPos].puesto : null;

      // ---- Resultado de la pregunta (¡Correcto! / Incorrecto + explicación) ----
      const ronda = $('ronda');
      if (correcta === null) {
        ronda.classList.add('oculto');
      } else {
        const textoCorrecta = opcionesActuales[correcta] ?? 'opción ' + (correcta + 1);
        let titulo;
        if (miEleccion === correcta) {
          ronda.className = 'ronda bien';
          titulo = '✅ ¡Correcto!';
          $('resultado-era').textContent = textoCorrecta;
        } else {
          ronda.className = 'ronda mal';
          titulo = (miEleccion === null || miEleccion === -1) ? '⏱️ No respondiste' : '❌ Incorrecto';
          $('resultado-era').textContent = 'Era: ' + textoCorrecta;
        }
        $('resultado-titulo').textContent = titulo;
        $('explicacion').textContent = textoExplicacion || '';
        $('ronda-resumen').textContent = 'Última pregunta: ' + titulo;
        // Durante la partida siempre abierto; al final, plegado (se abre tocando el resumen)
        ronda.open = !final;
      }

      // ---- Resumen: "Estás 2° · 1850 pts · +820 · a 550 de Ana" ----
      const resumen = $('resumen');
      resumen.innerHTML = '';
      if (miPos >= 0) {
        const mio = ranking[miPos];
        const empatados = ranking.filter((j) => j.puesto === miPuesto).length > 1;
        let texto = miPuesto === 1
          ? (empatados ? '¡Vas primero! (empatado)' : '¡Vas primero!')
          : 'Estás ' + miPuesto + '°' + (empatados ? ' (empatado)' : '');
        texto += ' · ' + mio.puntos + ' pts';
        resumen.appendChild(document.createTextNode(texto));

        if (correcta !== null) {
          const mas = document.createElement('span');
          mas.className = 'mas';
          mas.textContent = ' · +' + (mio.ganados || 0);
          resumen.appendChild(mas);
        }

        // El de arriba es el último del puesto anterior (el que tiene más puntos que yo)
        if (miPuesto > 1) {
          const arriba = ranking[miPuesto - 2];
          resumen.appendChild(document.createTextNode(' · a ' + (arriba.puntos - mio.puntos) + ' de ' + arriba.nombre));
        }
      } else {
        resumen.textContent = 'Todavía no estás en el ranking.';
      }

      dibujarRanking(ranking, miPos, correcta !== null);

      // ---- Fin de la partida: mensaje según el puesto ----
      $('pantalla-resultado').classList.toggle('es-final', !!final);
      $('final').classList.toggle('oculto', !final);
      if (final) {
        const titulos = ['¡Ganaste la partida!', '¡Segundo puesto!', '¡Tercer puesto!'];
        if (miPuesto !== null && miPuesto <= 3) {
          $('final-medalla').textContent = MEDALLAS[miPuesto - 1];
          $('final-titulo').textContent = titulos[miPuesto - 1];
        } else {
          $('final-medalla').textContent = '🏁';
          $('final-titulo').textContent = miPuesto !== null
            ? 'Terminaste ' + miPuesto + '° de ' + ranking.length
            : 'Terminó la partida';
        }
        $('final-detalle').textContent = miPos >= 0 ? ranking[miPos].puntos + ' puntos en total' : '';
        $('texto-siguiente').textContent = 'Esperando una nueva partida...';
      } else {
        $('texto-siguiente').textContent = 'Esperando la próxima pregunta...';
      }

      mostrarPantalla('resultado');
      // El scroll del ranking completo solo se puede calcular con la pantalla visible
      if (rankingAbierto) aplicarRankingAbierto(!$('btn-ranking').classList.contains('oculto'));
    }

    // ================================================================
    // ESTADO INICIAL
    // ================================================================

    // Pide al servidor el estado actual (para sumarse tarde o tras recargar)
    async function cargarEstado() {
      try {
        const r = await fetch(RUTAS.estado + '?nombre=' + encodeURIComponent(nombre), {
          headers: { 'Accept': 'application/json' },
        });
        const estado = await r.json();
        mostrarMisPuntosDe(estado.ranking);

        if (estado.pregunta) {
          mostrarPregunta({
            numero: estado.numero, total: estado.total,
            texto: estado.pregunta.texto, opciones: estado.pregunta.opciones, tema: estado.pregunta.tema,
            segundos: estado.segundos, cierraEn: estado.cierraEn, ahora: estado.ahora,
          });
          // Si ya había respondido antes de recargar, dejamos los botones bloqueados
          if (estado.miOpcion !== null) {
            miEleccion = estado.miOpcion;
            bloquearOpciones(miEleccion);
            mostrarMensaje('Ya respondiste. ¡Esperá el resultado!');
          }
        } else if (estado.terminada) {
          mostrarResultado(null, '', estado.ranking, true);
        } else {
          mostrarPantalla('espera');
        }
      } catch (e) {
        mostrarPantalla('espera');
      }
    }

    // Se anota (o se re-anota) y se pone al día con el estado
    async function entrar(nombreDeseado) {
      if (await unirse(nombreDeseado)) {
        await cargarEstado();
      }
    }

    // ---- Formulario de nombre ----
    $('pantalla-nombre').addEventListener('submit', (ev) => {
      ev.preventDefault();
      const valor = $('input-nombre').value.trim();
      if (!valor) return;
      // Si cambia de nombre, el token viejo ya no sirve
      if (valor.toLowerCase() !== nombre.toLowerCase()) token = '';
      entrar(valor);
    });

    // ================================================================
    // EVENTOS EN VIVO (Reverb + Echo)
    // ================================================================
    window.Echo.channel('trivia')
      .listen('.PreguntaLanzada', (e) => {
        if (!token) return; // todavía no se anotó
        mostrarPregunta(e);
      })
      .listen('.ResultadoRevelado', (e) => {
        if (!token) return;
        mostrarResultado(e.correcta, e.explicacion, e.ranking, e.final);
      })
      .listen('.PartidaReiniciada', () => {
        if (!token) return;
        // El servidor borró los jugadores: nos volvemos a anotar con el mismo nombre
        numeroActual = null;
        mostrarPantalla('espera');
        entrar(nombre);
      })
      .listen('.JugadorSalio', (e) => {
        // Solo nos importa si el que salió somos nosotros: el host nos quitó, o salimos
        // desde otra pestaña de este mismo navegador
        if (!token || String(e.nombre).toLowerCase() !== nombre.toLowerCase()) return;
        restaurarBotonSalir();
        olvidarJugador();
        pedirNombre(e.quitado ? 'El host te quitó de la partida. Podés volver a entrar con un nombre.' : '');
      });

    // ---- Al cargar ----
    if (nombre && token) {
      // Hay un jugador guardado: lo validamos con el servidor antes de entrar
      mostrarPantalla('espera');
      $('mi-nombre').textContent = nombre;
      reconectar();
    } else {
      pedirNombre('');
    }
  });
</script>
</body>
</html>
