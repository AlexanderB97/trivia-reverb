<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Una pregunta del banco de la trivia.
 *
 * @property int $id
 * @property string|null $tema
 * @property string $texto
 * @property array<int, string> $opciones
 * @property int $correcta
 * @property string $explicacion
 * @property \Illuminate\Support\Carbon|null $usada_en
 */
class Pregunta extends Model
{
    protected $table = 'preguntas';

    protected $fillable = ['tema', 'texto', 'texto_hash', 'opciones', 'correcta', 'explicacion', 'usada_en'];

    protected function casts(): array
    {
        return [
            'opciones' => 'array',
            'correcta' => 'integer',
            'usada_en' => 'datetime',
        ];
    }

    /** Preguntas que todavía no se usaron en ninguna partida. */
    public function scopeDisponibles(Builder $query): void
    {
        $query->whereNull('usada_en');
    }

    /** Normaliza un texto para comparar duplicados: minúsculas y espacios simples. */
    public static function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($texto)));
    }

    public static function hashDe(string $texto): string
    {
        return sha1(self::normalizar($texto));
    }

    /**
     * Guarda la pregunta si no existe otra con el mismo texto (normalizado).
     * Devuelve la pregunta guardada, o null si era repetida.
     *
     * @param  array{tema?: ?string, texto: string, opciones: array, correcta: int, explicacion?: string, usada_en?: mixed}  $datos
     */
    public static function guardarSiNoExiste(array $datos): ?self
    {
        $hash = self::hashDe($datos['texto']);

        if (self::where('texto_hash', $hash)->exists()) {
            return null;
        }

        try {
            return self::create([
                'tema' => $datos['tema'] ?? null,
                'texto' => $datos['texto'],
                'texto_hash' => $hash,
                'opciones' => array_values($datos['opciones']),
                'correcta' => $datos['correcta'],
                'explicacion' => $datos['explicacion'] ?? '',
                'usada_en' => $datos['usada_en'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otro proceso la guardó justo entre el exists() y el create()
            return null;
        }
    }

    /** Formato que usa el juego (el mismo que devolvía GeneradorPreguntas::generar()). */
    public function paraJuego(): array
    {
        return [
            'id' => $this->id,
            'texto' => $this->texto,
            'opciones' => $this->opciones,
            'correcta' => $this->correcta,
            'explicacion' => $this->explicacion,
            'tema' => $this->tema,
        ];
    }
}
