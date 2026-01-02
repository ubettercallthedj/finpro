<?php

namespace App\Observers;

use App\Models\Persona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PersonaObserver
{
    /**
     * Handle the Persona "created" event.
     */
    public function created(Persona $persona): void
    {
        $this->logAcceso($persona, 'creacion', 'Persona creada');
        
        // Auditoría
        $this->registrarAuditoria($persona, 'create', null, $persona->toArray());
    }

    /**
     * Handle the Persona "updated" event.
     */
    public function updated(Persona $persona): void
    {
        $this->logAcceso($persona, 'actualizacion', 'Persona actualizada');
        
        // Auditoría con cambios
        $cambios = $persona->getChanges();
        $original = $persona->getOriginal();
        
        $this->registrarAuditoria($persona, 'update', $original, $cambios);
    }

    /**
     * Handle the Persona "deleted" event.
     */
    public function deleted(Persona $persona): void
    {
        $this->logAcceso($persona, 'eliminacion', 'Persona eliminada (soft delete)');
        
        $this->registrarAuditoria($persona, 'delete', $persona->toArray(), null);
    }

    /**
     * Handle the Persona "restored" event.
     */
    public function restored(Persona $persona): void
    {
        $this->logAcceso($persona, 'restauracion', 'Persona restaurada');
        
        $this->registrarAuditoria($persona, 'restore', null, $persona->toArray());
    }

    /**
     * Handle the Persona "force deleted" event.
     */
    public function forceDeleted(Persona $persona): void
    {
        $this->logAcceso($persona, 'eliminacion_permanente', 'Persona eliminada permanentemente');
        
        $this->registrarAuditoria($persona, 'force_delete', $persona->toArray(), null);
    }

    /**
     * Registrar acceso a datos personales (Ley 19.628/21.719)
     */
    protected function logAcceso(Persona $persona, string $operacion, string $motivo): void
    {
        if (!Auth::check()) {
            return;
        }

        try {
            DB::table('log_acceso_datos_personales')->insert([
                'tenant_id' => $persona->tenant_id,
                'user_id' => Auth::id(),
                'tabla_accedida' => 'personas',
                'registro_id' => $persona->id,
                'persona_afectada_id' => $persona->id,
                'campos_accedidos' => json_encode(['*']),
                'operacion' => $operacion,
                'motivo' => $motivo,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'endpoint' => request()->path(),
                'exitoso' => true,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error logging acceso persona: ' . $e->getMessage());
        }
    }

    /**
     * Registrar en auditoría general
     */
    protected function registrarAuditoria(Persona $persona, string $evento, ?array $datosAnteriores, ?array $datosNuevos): void
    {
        try {
            DB::table('audit_logs')->insert([
                'tenant_id' => $persona->tenant_id,
                'user_id' => Auth::id(),
                'evento' => $evento,
                'modelo' => 'Persona',
                'modelo_id' => $persona->id,
                'datos_anteriores' => $datosAnteriores ? json_encode($datosAnteriores) : null,
                'datos_nuevos' => $datosNuevos ? json_encode($datosNuevos) : null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error registrando auditoría: ' . $e->getMessage());
        }
    }
}
