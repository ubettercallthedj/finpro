<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LogAccesoDatosPersonales
{
    protected array $endpointsSensibles = [
        'personas',
        'empleados', 
        'liquidaciones',
        'unidades',
        'arrendatarios',
        'contratos',
        'distribucion',
        'certificados',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() && $this->esEndpointSensible($request)) {
            try {
                $this->registrarAcceso($request, $response);
            } catch (\Exception $e) {
                \Log::error('Error logging acceso datos: ' . $e->getMessage());
            }
        }

        return $response;
    }

    protected function esEndpointSensible(Request $request): bool
    {
        $path = $request->path();
        
        foreach ($this->endpointsSensibles as $endpoint) {
            if (str_contains($path, $endpoint)) {
                return true;
            }
        }
        
        return false;
    }

    protected function registrarAcceso(Request $request, Response $response): void
    {
        $operacion = match($request->method()) {
            'GET' => 'lectura',
            'POST' => 'creacion',
            'PUT', 'PATCH' => 'actualizacion',
            'DELETE' => 'eliminacion',
            default => 'lectura',
        };

        $pathParts = explode('/', trim($request->path(), '/'));
        $tabla = $pathParts[1] ?? 'desconocida';
        
        $registroId = 0;
        foreach ($pathParts as $part) {
            if (is_numeric($part)) {
                $registroId = (int) $part;
                break;
            }
        }

        $personaAfectadaId = $request->input('persona_id') 
            ?? $request->input('propietario_id')
            ?? $request->input('beneficiario_id')
            ?? null;

        // Solo loguear si hay usuario autenticado
        if (!Auth::check()) {
            return;
        }

        DB::table('log_acceso_datos_personales')->insert([
            'tenant_id' => Auth::user()->tenant_id ?? 1,
            'user_id' => Auth::id(),
            'tabla_accedida' => str_replace('-', '_', $tabla),
            'registro_id' => $registroId,
            'persona_afectada_id' => $personaAfectadaId,
            'campos_accedidos' => json_encode(array_keys($request->all())),
            'operacion' => $operacion,
            'motivo' => $request->input('motivo_acceso'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
            'exitoso' => true,
            'created_at' => now(),
        ]);
    }
}
