<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActualizarMoraCommand extends Command
{
    protected $signature = 'mora:actualizar';
    protected $description = 'Actualiza días de mora y estados de boletas de gastos comunes';

    public function handle()
    {
        $this->info('Actualizando días de mora...');

        $hoy = Carbon::now()->startOfDay();
        
        // Actualizar boletas pendientes y parciales
        $boletas = DB::table('boletas_gc')
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->whereNotNull('fecha_vencimiento')
            ->get();

        $actualizadas = 0;
        $vencidas = 0;

        foreach ($boletas as $boleta) {
            $fechaVencimiento = Carbon::parse($boleta->fecha_vencimiento);
            
            if ($hoy->greaterThan($fechaVencimiento)) {
                $diasMora = $hoy->diffInDays($fechaVencimiento);
                
                $saldoPendiente = $boleta->total_a_pagar - ($boleta->total_abonos ?? 0);
                
                // Calcular intereses si aplica
                $edificio = DB::table('edificios')->find($boleta->edificio_id);
                $tasaInteresMensual = $edificio->interes_mora ?? 1.5;
                $intereses = 0;
                
                if ($diasMora > 0 && $saldoPendiente > 0) {
                    $mesesMora = ceil($diasMora / 30);
                    $intereses = round($saldoPendiente * ($tasaInteresMensual / 100) * $mesesMora, 0);
                }
                
                DB::table('boletas_gc')
                    ->where('id', $boleta->id)
                    ->update([
                        'dias_mora' => $diasMora,
                        'total_intereses' => $intereses,
                        'estado' => $diasMora > 0 ? 'vencida' : $boleta->estado,
                        'updated_at' => now(),
                    ]);
                
                $actualizadas++;
                if ($diasMora > 0) {
                    $vencidas++;
                }
            }
        }

        $this->info("✓ Procesadas {$actualizadas} boletas");
        $this->info("✓ {$vencidas} boletas marcadas como vencidas");
        
        return 0;
    }
}
