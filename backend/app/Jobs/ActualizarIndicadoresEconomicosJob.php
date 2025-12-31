<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ActualizarIndicadoresEconomicosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function handle(): void
    {
        \Log::info("Actualizando indicadores económicos desde API externa");

        try {
            // Usando API de mindicador.cl (gratis, mantenida por Banco Central)
            $response = Http::timeout(30)->get('https://mindicador.cl/api');

            if ($response->successful()) {
                $data = $response->json();
                
                // UF
                if (isset($data['uf']['valor'])) {
                    $this->guardarIndicador('UF', $data['uf']['valor'], $data['uf']['fecha']);
                }

                // UTM
                if (isset($data['utm']['valor'])) {
                    $this->guardarIndicador('UTM', $data['utm']['valor'], $data['utm']['fecha']);
                }

                // IPC (variación mensual)
                if (isset($data['ipc']['valor'])) {
                    $this->guardarIndicador('IPC', $data['ipc']['valor'], $data['ipc']['fecha']);
                }

                // USD
                if (isset($data['dolar']['valor'])) {
                    $this->guardarIndicador('USD', $data['dolar']['valor'], $data['dolar']['fecha']);
                }

                // EUR
                if (isset($data['euro']['valor'])) {
                    $this->guardarIndicador('EUR', $data['euro']['valor'], $data['euro']['fecha']);
                }

                \Log::info("Indicadores económicos actualizados correctamente");

            } else {
                \Log::warning("API de indicadores respondió con código: " . $response->status());
            }

        } catch (\Exception $e) {
            \Log::error("Error actualizando indicadores: " . $e->getMessage());
            throw $e; // Re-lanzar para retry
        }
    }

    protected function guardarIndicador(string $codigo, float $valor, string $fecha): void
    {
        DB::table('indicadores_economicos')->updateOrInsert(
            [
                'codigo' => $codigo,
                'fecha' => date('Y-m-d', strtotime($fecha))
            ],
            [
                'valor' => $valor,
                'fuente' => 'mindicador.cl',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("Job ActualizarIndicadoresEconomicosJob falló: " . $exception->getMessage());
    }
}
