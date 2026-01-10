<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $edificioId = $request->get('edificio_id');

        $stats = [
            'total_unidades' => DB::table('unidades')
                ->where('tenant_id', $tenantId)
                ->when($edificioId, fn($q) => $q->where('edificio_id', $edificioId))
                ->count(),

            'recaudacion_mes' => DB::table('pagos_gc')
                ->join('boletas_gc', 'pagos_gc.boleta_id', '=', 'boletas_gc.id')
                ->where('boletas_gc.tenant_id', $tenantId)
                ->when($edificioId, fn($q) => $q->where('boletas_gc.edificio_id', $edificioId))
                ->whereMonth('pagos_gc.fecha_pago', now()->month)
                ->whereYear('pagos_gc.fecha_pago', now()->year)
                ->sum('pagos_gc.monto'),

            'morosidad_total' => DB::table('boletas_gc')
                ->where('tenant_id', $tenantId)
                ->when($edificioId, fn($q) => $q->where('edificio_id', $edificioId))
                ->whereIn('estado', ['pendiente', 'vencida'])
                ->sum(DB::raw('total_a_pagar - COALESCE(total_abonos, 0)')),

            'contratos_activos' => DB::table('contratos_arriendo')
                ->where('tenant_id', $tenantId)
                ->when($edificioId, fn($q) => $q->where('edificio_id', $edificioId))
                ->where('estado', 'activo')
                ->count(),

            'empleados_activos' => DB::table('empleados')
                ->where('tenant_id', $tenantId)
                ->where('estado', 'activo')
                ->count(),

            'reuniones_programadas' => DB::table('reuniones')
                ->where('tenant_id', $tenantId)
                ->when($edificioId, fn($q) => $q->where('edificio_id', $edificioId))
                ->where('fecha_inicio', '>', now())
                ->whereIn('estado', ['programada', 'convocada'])
                ->count(),
        ];

        return response()->json($stats);
    }

    public function morosidad(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        $morosos = DB::table('boletas_gc')
            ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
            ->leftJoin('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->leftJoin('edificios', 'unidades.edificio_id', '=', 'edificios.id')
            ->where('boletas_gc.tenant_id', $tenantId)
            ->whereIn('boletas_gc.estado', ['pendiente', 'vencida'])
            ->select(
                'unidades.numero',
                'personas.nombre_completo as propietario',
                'edificios.nombre as edificio',
                DB::raw('SUM(boletas_gc.total_a_pagar - COALESCE(boletas_gc.total_abonos, 0)) as deuda'),
                DB::raw('MAX(boletas_gc.dias_mora) as dias_mora')
            )
            ->groupBy('unidades.id', 'unidades.numero', 'personas.nombre_completo', 'edificios.nombre')
            ->havingRaw('SUM(boletas_gc.total_a_pagar - COALESCE(boletas_gc.total_abonos, 0)) > 0')
            ->orderByDesc('deuda')
            ->limit(20)
            ->get();

        return response()->json($morosos);
    }

    public function ingresos(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $meses = $request->get('meses', 12);

        $ingresos = collect();
        for ($i = $meses - 1; $i >= 0; $i--) {
            $fecha = now()->subMonths($i);

            $gc = DB::table('pagos_gc')
                ->join('boletas_gc', 'pagos_gc.boleta_id', '=', 'boletas_gc.id')
                ->where('boletas_gc.tenant_id', $tenantId)
                ->whereMonth('pagos_gc.fecha_pago', $fecha->month)
                ->whereYear('pagos_gc.fecha_pago', $fecha->year)
                ->sum('pagos_gc.monto');

            $arriendos = DB::table('facturas_arriendo')
                ->where('tenant_id', $tenantId)
                ->whereMonth('fecha_pago', $fecha->month)
                ->whereYear('fecha_pago', $fecha->year)
                ->sum('monto_total');

            $ingresos->push([
                'mes' => $fecha->format('Y-m'),
                'nombre' => $fecha->locale('es')->monthName,
                'gastos_comunes' => $gc,
                'arriendos' => $arriendos,
                'total' => $gc + $arriendos,
            ]);
        }

        return response()->json($ingresos);
    }

    public function alertas(Request $request): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;
        $alertas = [];

        // Boletas vencidas
        $vencidas = DB::table('boletas_gc')
            ->where('tenant_id', $tenantId)
            ->where('estado', 'vencida')
            ->where('dias_mora', '>', 30)
            ->count();

        if ($vencidas > 0) {
            $alertas[] = [
                'tipo' => 'error',
                'mensaje' => "{$vencidas} boletas con más de 30 días de mora",
                'accion' => '/gastos-comunes/morosidad',
            ];
        }

        // Contratos por vencer
        $contratos = DB::table('contratos_arriendo')
            ->where('tenant_id', $tenantId)
            ->where('estado', 'activo')
            ->whereBetween('fecha_termino', [now(), now()->addDays(60)])
            ->count();

        if ($contratos > 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'mensaje' => "{$contratos} contratos vencen en 60 días",
                'accion' => '/arriendos/contratos',
            ];
        }

        return response()->json($alertas);
    }
}
