<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Receptor</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    body { font-family: system-ui, sans-serif; padding: 24px; }
    .wrap { max-width: 760px; margin: 0 auto; }
    h1 { font-size: 28px; font-weight: 700; margin-bottom: 12px; }
    #target-div { padding: 20px; border: 1px dashed #bbb; border-radius: 12px; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Receptor</h1>
  <div id="target-div">Esperando mensaje...</div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const targetDiv = document.getElementById('target-div');

    window.Echo.channel('style-channel')
      .listen('.RemoteStyleUpdate', (e) => {
        console.log('Llegó el evento:', e);
        targetDiv.innerText = e.newText ?? '';
        targetDiv.style.cssText = e.newStyle ?? '';
      });
  });
</script>
</body>
</html>
