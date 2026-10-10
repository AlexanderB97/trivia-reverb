{{--
  Layout de las páginas de error (403, 404, 419, 429, 500, 503).

  Tiene que verse bien aunque el build de Vite falle o haya un deploy en curso, así que
  NO depende de nada externo: todo el CSS va acá adentro, no usa la directiva de Vite,
  ni archivos compilados, ni fuentes, ni imágenes. Los links van escritos a mano
  ("/jugar", "/host") para no depender tampoco del generador de rutas.

  Secciones que completa cada vista:
    codigo   número del error (cada dígito toma un color de las opciones del juego)
    emoji    emoji grande arriba del título
    titulo   título corto
    mensaje  explicación en una o dos oraciones
    boton    botón principal (y, si hace falta, un link secundario)
    head     opcional: etiquetas extra para el <head> (la 503 se recarga sola)
    extra    opcional: algo entre el mensaje y el botón (la animación de la 503)
--}}
@php($codigo = trim($__env->yieldContent('codigo')))
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>@yield('titulo') · Trivia en vivo</title>
  @yield('head')
  <style>
    /* Mismos colores que /jugar y /host */
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
      --violeta: #6c5ce7;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0; min-height: 100vh;
      display: flex; align-items: center; justify-content: center; padding: 16px;
      background: radial-gradient(circle at top, #23265a 0%, var(--fondo) 60%);
      color: var(--texto);
      font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
      -webkit-tap-highlight-color: transparent;
    }

    .tarjeta {
      width: 100%; max-width: 440px; text-align: center;
      background: var(--panel); border: 1px solid var(--borde); border-radius: 20px;
      padding: 28px 22px 24px;
    }
    .marca { margin: 0 0 14px; font-size: 16px; font-weight: 800; letter-spacing: .5px; color: var(--suave); }
    .marca span { color: var(--amarillo); }

    /* Número del error: cada dígito con el color de una opción, con la sombra de sus botones */
    .codigo {
      margin: 0; line-height: 1; font-weight: 800; letter-spacing: 4px;
      font-size: clamp(84px, 30vw, 132px);
      text-shadow: 0 6px 0 rgba(0, 0, 0, .35);
    }
    .codigo .op-0 { color: var(--rojo); }
    .codigo .op-1 { color: var(--azul); }
    .codigo .op-2 { color: var(--amarillo); }
    .codigo .op-3 { color: var(--verde); }

    /* Las cuatro formas de las opciones (▲ ◆ ● ■), como en los botones para responder */
    .formas { display: flex; justify-content: center; gap: 10px; margin: 14px 0 18px; }
    .formas span {
      width: 38px; height: 38px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 17px; box-shadow: 0 4px 0 rgba(0, 0, 0, .35);
    }
    .formas .op-0 { background: var(--rojo); }
    .formas .op-1 { background: var(--azul); }
    .formas .op-2 { background: var(--amarillo); }
    .formas .op-3 { background: var(--verde); }

    .emoji { font-size: 44px; line-height: 1; margin: 0 0 8px; }
    h1 { font-size: 26px; font-weight: 800; line-height: 1.2; margin: 0 0 10px; }
    .mensaje { margin: 0 0 22px; color: var(--suave); font-size: 17px; line-height: 1.5; }

    .btn {
      display: block; width: 100%;
      font: inherit; font-size: 20px; font-weight: 800; text-align: center; text-decoration: none;
      padding: 16px; border: 0; border-radius: 14px; cursor: pointer;
      background: var(--violeta); color: #fff;
      box-shadow: 0 6px 0 rgba(0, 0, 0, .35);
      transition: transform .1s, box-shadow .1s;
    }
    .btn:active { transform: translateY(4px); box-shadow: 0 2px 0 rgba(0, 0, 0, .35); }
    .btn:focus-visible, .link:focus-visible { outline: 3px solid #fff; outline-offset: 3px; }
    .link {
      display: inline-block; margin-top: 18px; padding: 6px;
      font: inherit; font-size: 15px; font-weight: 600;
      background: none; border: 0; cursor: pointer;
      color: var(--suave); text-decoration: underline;
    }

    /* Puntos que rebotan, los mismos de "Esperando pregunta..." */
    .puntos { display: flex; gap: 8px; justify-content: center; margin: 0 0 22px; }
    .puntos i { width: 14px; height: 14px; border-radius: 50%; background: var(--amarillo); animation: rebote 1s infinite; }
    .puntos i:nth-child(2) { animation-delay: .15s; background: var(--azul); }
    .puntos i:nth-child(3) { animation-delay: .3s; background: var(--rojo); }
    @keyframes rebote { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    @media (prefers-reduced-motion: reduce) {
      .puntos i { animation: none; }
      .btn { transition: none; }
    }

    @media (max-width: 380px) {
      .tarjeta { padding: 22px 16px 20px; }
      h1 { font-size: 22px; }
      .mensaje { font-size: 16px; }
      .btn { font-size: 18px; }
    }
  </style>
</head>
<body>
  <main class="tarjeta">
    <p class="marca">Trivia <span>en vivo</span></p>

    <p class="codigo" aria-label="Error {{ $codigo }}">@foreach (str_split($codigo) as $i => $digito)<span class="op-{{ $i % 4 }}" aria-hidden="true">{{ $digito }}</span>@endforeach</p>

    <div class="formas" aria-hidden="true">
      <span class="op-0">▲</span><span class="op-1">◆</span><span class="op-2">●</span><span class="op-3">■</span>
    </div>

    <p class="emoji" aria-hidden="true">@yield('emoji')</p>
    <h1>@yield('titulo')</h1>
    <p class="mensaje">@yield('mensaje')</p>

    @yield('extra')

    @yield('boton')
  </main>

  <script>
    // Botones con data-recargar ("Recargar", "Volver a intentar").
    // Si el error salió al enviar un formulario (por ejemplo el PIN del host), recargar
    // reenviaría el mismo formulario vencido: en ese caso se vuelve a la página anterior,
    // que se carga de nuevo. Si no, se recarga esta misma dirección.
    document.querySelectorAll('[data-recargar]').forEach(function (boton) {
      boton.addEventListener('click', function (ev) {
        ev.preventDefault();
        var anterior = document.referrer;
        if (anterior && anterior.indexOf(location.origin) === 0 && anterior !== location.href) {
          location.replace(anterior);
        } else {
          location.reload();
        }
      });
    });
  </script>
</body>
</html>
