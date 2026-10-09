<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Trivia en vivo - PIN del host</title>
  @vite(['resources/css/app.css'])
  <style>
    /* Estilos propios (Tailwind resetea tamaños de títulos y botones) */
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh;
      display: flex; align-items: center; justify-content: center; padding: 16px;
      background: radial-gradient(circle at top, #23265a 0%, #0f1020 60%);
      color: #f2f3ff; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    }
    form {
      width: 100%; max-width: 380px; display: flex; flex-direction: column; gap: 14px;
      background: #1b1d36; border: 1px solid #2c2f55; border-radius: 20px; padding: 28px 22px;
    }
    h1 { font-size: 28px; font-weight: 800; margin: 0; text-align: center; }
    h1 span { color: #d89e00; }
    p { margin: 0; text-align: center; color: #9aa0c8; }
    input {
      font: inherit; font-size: 24px; letter-spacing: 6px; text-align: center; width: 100%;
      padding: 14px; border-radius: 14px; outline: none;
      border: 2px solid #2c2f55; background: #0f1020; color: #f2f3ff;
    }
    input:focus { border-color: #6c5ce7; }
    button {
      font: inherit; font-size: 18px; font-weight: 800; width: 100%;
      padding: 14px; border: 0; border-radius: 14px; background: #6c5ce7; color: #fff; cursor: pointer;
    }
    .error {
      color: #ff5c74; background: rgba(255, 92, 116, .15); border: 1px solid #ff5c74;
      border-radius: 12px; padding: 10px; font-weight: 600;
    }
  </style>
</head>
<body>
  <form method="POST" action="{{ route('trivia.login') }}">
    @csrf
    <h1>Trivia <span>en vivo</span></h1>
    <p>Pantalla del host: ingresá el PIN</p>

    @if (! $pinConfigurado)
      <p class="error">Falta configurar TRIVIA_HOST_PIN en el .env.</p>
    @endif

    @if (session('pin_error'))
      <p class="error">{{ session('pin_error') }}</p>
    @endif

    @error('pin')
      <p class="error">{{ $message }}</p>
    @enderror

    <input type="password" name="pin" inputmode="numeric" autocomplete="off" placeholder="PIN" required autofocus>
    <button type="submit">Entrar</button>
  </form>
</body>
</html>
