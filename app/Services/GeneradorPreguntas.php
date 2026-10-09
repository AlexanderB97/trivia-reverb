<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeneradorPreguntas
{
    public function generar(array $yaHechas = []): array
    {
        $evitar = $yaHechas
            ? "No repitas ninguna de estas preguntas:\n- " . implode("\n- ", $yaHechas)
            : '';
$prompt = <<<PROMPT
Generá UNA pregunta de trivia en español sobre programación web con Laravel
o sobre bases de datos (SQL, migraciones, Eloquent, relaciones, índices, etc.).
Nivel: estudiante de una tecnicatura universitaria en programación.
Tiene que tener exactamente 4 opciones cortas (máximo 60 caracteres cada una)
y una sola correcta.
{$evitar}
Respondé SOLO con un JSON con este formato:
{"texto": "...", "opciones": ["...", "...", "...", "..."], "correcta": 0, "explicacion": "..."}
"correcta" es la posición (0 a 3) de la opción correcta.
PROMPT;

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . config('services.gemini.model') . ':generateContent';

        $respuesta = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->timeout(30)
            ->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 1.0,
                ],
            ]);

        if ($respuesta->failed()) {
            throw new RuntimeException('La IA respondió con error: ' . $respuesta->body());
        }

        $texto = $respuesta->json('candidates.0.content.parts.0.text');
        $pregunta = json_decode($texto ?? '', true);

        if (
            ! is_array($pregunta)
            || empty($pregunta['texto'])
            || ! isset($pregunta['opciones']) || count($pregunta['opciones']) !== 4
            || ! isset($pregunta['correcta']) || ! in_array($pregunta['correcta'], [0, 1, 2, 3], true)
        ) {
            throw new RuntimeException('La IA devolvió un formato inválido: ' . $texto);
        }

        return $pregunta;
    }
}