<?php

namespace App\Observers;

use App\Models\BoletaGC;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Jobs\GenerarBoletasPDFJob;

class BoletaObserver
{
    /**
     * Handle the BoletaGC "created" event.
     */
    public function created(BoletaGC $boleta): void
    {
        // Generar número de boleta si no existe
        if (!$boleta->numero_boleta) {
            $edificio = DB::table('edificios')->find($boleta->edificio_id);
            $periodo = DB::table('periodos_gc')->find($boleta->periodo_id);
            
            $numeroBoleta = sprintf(
                '%s-%04d-%02d-%04d',
                $edificio->rut ?? 'SIN-RUT',
                $periodo->anio ?? date('Y'),
                $periodo->mes ?? date('m'),
                $boleta->id
            );
            
            $boleta->update(['numero_boleta' => $numeroBoleta]);
        }

        // Disparar generación de PDF en segundo plano
        GenerarBoletasPDFJob::dispatch($boleta->periodo_id)->delay(now()->addMinutes(2));

        // Auditoría
        $this->registrarAuditoria($boleta, 'create', null, $boleta->toArray());
    }

    /**
     * Handle the BoletaGC "updated" event.
     */
    public function updated(BoletaGC $boleta): void
    {
        // Si cambió el estado a "pagada", notificar
        if ($boleta->wasChanged('estado') && $boleta->estado === 'pagada') {
            $this->notificarPagoRecibido($boleta);
        }

        // Si cambió a "vencida", aplicar intereses
        if ($boleta->wasChanged('estado') && $boleta->estado === 'vencida') {
            $this->calcularInteresesMora($boleta);
        }

        // Auditoría
        $cambios = $boleta->getChanges();
        $original = $boleta->getOriginal();
        $this->registrarAuditoria($boleta, 'update', $original, $cambios);
    }

    /**
     * Handle the BoletaGC "deleted" event.
     */
    public function deleted(BoletaGC $boleta): void
    {
        $this->registrarAuditoria($boleta, 'delete', $boleta->toArray(), null);
    }

    /**
     * Notificar pago recibido
     */
    protected function notificarPagoRecibido(BoletaGC $boleta): void
    {
        $unidad = DB::table('unidades')
            ->join('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->where('unidades.id', $boleta->unidad_id)
            ->select('personas.email', 'personas.nombre_completo', 'unidades.numero')
            ->first();

        if ($unidad && $unidad->email) {
            DB::table('notificaciones')->insert([
                'tenant_id' => $boleta->tenant_id,
                'user_id' => null,
                'tipo' => 'pago_recibido',
                'titulo' => '✅ Pago Recibido',
                'mensaje' => "Su pago de la boleta {$boleta->numero_boleta} ha sido confirmado.",
                'data' => json_encode(['boleta_id' => $boleta->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \Log::info("Notificación de pago enviada: {$unidad->email}");
        }
    }

    /**
     * Calcular intereses por mora
     */
    protected function calcularInteresesMora(BoletaGC $boleta): void
    {
        $edificio = DB::table('edificios')->find($boleta->edificio_id);
        $tasaInteresMensual = $edificio->interes_mora ?? 1.5;
        
        $saldoPendiente = $boleta->total_a_pagar - ($boleta->total_abonos ?? 0);
        $diasMora = $boleta->dias_mora ?? 0;
        
        if ($diasMora > 0 && $saldoPendiente > 0) {
            $mesesMora = ceil($diasMora / 30);
            $intereses = round($saldoPendiente * ($tasaInteresMensual / 100) * $mesesMora, 0);
            
            $boleta->update([
                'total_intereses' => $intereses,
                'total_a_pagar' => $boleta->total_a_pagar + $intereses
            ]);

            \Log::info("Intereses calculados para boleta {$boleta->id}: \${$intereses}");
        }
    }

    /**
     * Registrar en auditoría
     */
    protected function registrarAuditoria(BoletaGC $boleta, string $evento, ?array $datosAnteriores, ?array $datosNuevos): void
    {
        try {
            DB::table('audit_logs')->insert([
                'tenant_id' => $boleta->tenant_id,
                'user_id' => Auth::id(),
                'evento' => $evento,
                'modelo' => 'BoletaGC',
                'modelo_id' => $boleta->id,
                'datos_anteriores' => $datosAnteriores ? json_encode($datosAnteriores) : null,
                'datos_nuevos' => $datosNuevos ? json_encode($datosNuevos) : null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error registrando auditoría boleta: ' . $e->getMessage());
        }
    }
}
