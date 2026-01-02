<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerarBoletasPDFJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos
    public $tries = 3;

    protected int $periodoId;

    public function __construct(int $periodoId)
    {
        $this->periodoId = $periodoId;
    }

    public function handle(): void
    {
        \Log::info("Iniciando generación de PDFs para período {$this->periodoId}");

        $boletas = DB::table('boletas_gc')
            ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->join('edificios', 'boletas_gc.edificio_id', '=', 'edificios.id')
            ->leftJoin('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->where('boletas_gc.periodo_id', $this->periodoId)
            ->whereNull('boletas_gc.archivo_pdf')
            ->select(
                'boletas_gc.*',
                'unidades.numero as unidad',
                'periodos_gc.mes',
                'periodos_gc.anio',
                'edificios.nombre as edificio',
                'edificios.direccion',
                'edificios.rut as edificio_rut',
                'personas.nombre_completo as propietario',
                'personas.rut as propietario_rut'
            )
            ->get();

        $generados = 0;
        $errores = 0;

        foreach ($boletas as $boleta) {
            try {
                // Obtener cargos
                $cargos = DB::table('cargos_gc')
                    ->where('boleta_id', $boleta->id)
                    ->get();

                // Generar PDF
                $pdf = Pdf::loadView('pdf.boleta-gc', [
                    'boleta' => $boleta,
                    'cargos' => $cargos
                ]);

                // Guardar en storage
                $filename = "boletas/{$boleta->edificio_id}/{$boleta->periodo_id}/boleta-{$boleta->id}.pdf";
                Storage::disk('public')->put($filename, $pdf->output());

                // Actualizar registro
                DB::table('boletas_gc')
                    ->where('id', $boleta->id)
                    ->update([
                        'archivo_pdf' => $filename,
                        'updated_at' => now()
                    ]);

                $generados++;

            } catch (\Exception $e) {
                \Log::error("Error generando PDF boleta {$boleta->id}: " . $e->getMessage());
                $errores++;
            }
        }

        \Log::info("PDFs generados: {$generados}, Errores: {$errores}");

        // Actualizar estado del período
        DB::table('periodos_gc')
            ->where('id', $this->periodoId)
            ->update(['pdfs_generados' => true]);
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("Job GenerarBoletasPDFJob falló para período {$this->periodoId}: " . $exception->getMessage());
    }
}
