<?php

namespace App\Console\Commands;

use App\Support\Recordatorios;
use Illuminate\Console\Command;

/**
 * Envía los recordatorios pendientes: citas próximas, tareas vencidas y consentimientos sin
 * firmar. Lo ejecuta el programador de tareas cada cinco minutos (routes/console.php); se
 * puede correr a mano sin riesgo, porque cada recordatorio sale una sola vez.
 */
class EnviarRecordatorios extends Command
{
    protected $signature = 'sigabu:recordatorios';

    protected $description = 'Envía los recordatorios de citas, tareas vencidas y consentimientos pendientes';

    public function handle(Recordatorios $recordatorios): int
    {
        $enviados = $recordatorios->enviarPendientes();

        $this->components->twoColumnDetail('Recordatorios de cita', (string) $enviados['citas']);
        $this->components->twoColumnDetail('Tareas vencidas', (string) $enviados['tareas_vencidas']);
        $this->components->twoColumnDetail('Consentimientos por firmar', (string) $enviados['consentimientos_por_firmar']);
        $this->components->twoColumnDetail('Contrafirmas pendientes', (string) $enviados['contrafirmas_pendientes']);

        return self::SUCCESS;
    }
}
