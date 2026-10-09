<?php

namespace App\Console\Commands;

use App\Models\Pregunta;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trivia:reciclar')]
#[Description('Marca todas las preguntas del banco como no usadas, para volver a usarlas')]
class ReciclarPreguntas extends Command
{
    public function handle(): int
    {
        $recicladas = Pregunta::whereNotNull('usada_en')->update(['usada_en' => null]);

        $this->info("Preguntas recicladas: {$recicladas}");
        $this->info('Preguntas sin usar en el banco: ' . Pregunta::disponibles()->count());

        return self::SUCCESS;
    }
}
