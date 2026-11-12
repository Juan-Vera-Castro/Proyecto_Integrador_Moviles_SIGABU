<?php

use App\Models\Persona;
use App\Models\Vbg\Transcripcion;
use App\Support\AlmacenEvidencias;
use App\Support\Vbg\TranscriptorAudios;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Programador de tareas. En el servidor lo ejecuta el contenedor "scheduler"
| (php artisan schedule:work); en local, "composer run dev".
*/
Schedule::command('sigabu:recordatorios')
    ->everyFiveMinutes()
    ->withoutOverlapping(15);

// Archivos de Inclusión subidos que nunca se guardaron con su formulario (specs/inclusion-formatos).
Schedule::command('inclusion:limpiar-temporales')->dailyAt('03:00')->timezone('America/Bogota');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('usuario:password {correo}', function (string $correo) {
    $persona = Persona::where('correo_inst', $correo)->first();

    if (! $persona || ! $persona->usuario) {
        $this->error("No existe un usuario asociado al correo {$correo}.");

        return 1;
    }

    $password = $this->secret('Nueva contraseña');

    if (! $password || $password !== $this->secret('Confirma la contraseña')) {
        $this->error('Las contraseñas no coinciden o están vacías.');

        return 1;
    }

    // El cast "hashed" de User genera el hash bcrypt que espera el login.
    $persona->usuario->contrasena_hash = $password;
    $persona->usuario->save();

    $this->info("Contraseña actualizada para {$correo}.");
})->purpose('Asignar una contraseña (bcrypt) a un usuario por su correo institucional');

Artisan::command('inclusion:limpiar-temporales {--horas=24}', function (AlmacenEvidencias $almacen) {
    $this->info('Evidencias temporales descartadas: '.$almacen->limpiarTemporales((int) $this->option('horas')));
})->purpose('Descartar los archivos de Inclusión que se subieron y nunca se guardaron con su formulario');

// Transcripción automática de los audios VBG (contemplada en la rama VIOLENCIA_GENERO). No hace
// nada mientras VBG_TRANSCRIPCION_COMANDO esté vacío; ver docs/vbg-ia/README.md.
Artisan::command('vbg:transcribir {--limite=5}', function (TranscriptorAudios $transcriptor) {
    if (! Transcripcion::iaDisponible()) {
        $this->warn('La transcripción automática no está configurada (VBG_TRANSCRIPCION_COMANDO).');

        return 0;
    }

    $resultado = $transcriptor->procesarPendientes((int) $this->option('limite'));
    $this->info("Transcripciones completadas: {$resultado['completadas']} · con error: {$resultado['errores']}");
})->purpose('Transcribir los audios VBG que están en cola');
