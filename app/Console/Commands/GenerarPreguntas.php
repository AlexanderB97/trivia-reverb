<?php

namespace App\Console\Commands;

use App\Models\Pregunta;
use App\Services\GeneradorPreguntas;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('trivia:generar {cantidad=30 : Cuántas preguntas pedir a la IA (en lotes de 10)}')]
#[Description('Genera preguntas con Gemini y las guarda en el banco de la trivia')]
class GenerarPreguntas extends Command
{
    private const TAMANO_LOTE = 10;

    public function handle(GeneradorPreguntas $generador): int
    {
        $cantidad = (int) $this->argument('cantidad');

        if ($cantidad < 1) {
            $this->error('La cantidad tiene que ser mayor a 0.');

            return self::FAILURE;
        }

        // Reintentos ante 503 y lotes cortados: los avisos ("Gemini saturado...") se muestran acá
        $generador->conReintentos(fn (string $mensaje) => $this->warn("  {$mensaje}"));

        $lotes = (int) ceil($cantidad / self::TAMANO_LOTE);
        $guardadas = 0;
        $cuotaAgotada = false;

        $this->info("Generando {$cantidad} preguntas en {$lotes} lote(s) de hasta " . self::TAMANO_LOTE . '...');

        for ($lote = 1; $lote <= $lotes; $lote++) {
            $pedir = min(self::TAMANO_LOTE, $cantidad - ($lote - 1) * self::TAMANO_LOTE);
            $this->line("Lote {$lote}/{$lotes}: pidiendo {$pedir} preguntas a la IA...");

            $inicio = microtime(true);

            try {
                $nuevas = $generador->generarLote($pedir);
            } catch (Throwable $e) {
                if ($e instanceof RuntimeException && $e->getCode() === GeneradorPreguntas::CUOTA_AGOTADA) {
                    $cuotaAgotada = true;
                    break;
                }

                // Otro error (503 tras 3 reintentos, timeout, formato raro...): avisamos en una línea y seguimos
                $this->warn("  Lote {$lote} falló: " . trim((string) preg_replace('/\s+/', ' ', mb_substr($e->getMessage(), 0, 200))));

                continue;
            }

            $guardadas += $nuevas;
            $descartadas = $pedir - $nuevas;
            $this->line(sprintf(
                '  Guardadas %d de %d%s (%.1f s)',
                $nuevas,
                $pedir,
                $descartadas > 0 ? " ({$descartadas} inválidas o repetidas)" : '',
                microtime(true) - $inicio,
            ));
        }

        $this->newLine();

        if ($cuotaAgotada) {
            $this->error('Gemini respondió 429: se agotó la cuota diaria del modelo ' . config('services.gemini.model') . '.');
            $this->line('Se conservan las preguntas que ya se habían guardado. Volvé a intentar cuando se renueve la cuota.');
        }

        $this->info("Preguntas guardadas en esta corrida: {$guardadas}");
        $this->info('Preguntas sin usar en el banco: ' . Pregunta::disponibles()->count());

        return $cuotaAgotada ? self::FAILURE : self::SUCCESS;
    }
}
