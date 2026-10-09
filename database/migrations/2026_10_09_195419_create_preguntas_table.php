<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Banco de preguntas de la trivia (generadas por lotes con la IA).
     */
    public function up(): void
    {
        Schema::create('preguntas', function (Blueprint $table) {
            $table->id();
            $table->string('tema')->nullable();
            $table->text('texto');
            // sha1 del texto normalizado (minúsculas, sin espacios extra).
            // El índice único impide guardar dos veces la misma pregunta.
            $table->char('texto_hash', 40)->unique();
            $table->json('opciones');
            $table->unsignedTinyInteger('correcta'); // 0 a 3
            $table->text('explicacion');
            $table->timestamp('usada_en')->nullable()->index(); // null = disponible
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preguntas');
    }
};
