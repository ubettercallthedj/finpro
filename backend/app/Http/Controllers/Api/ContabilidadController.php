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

class ContabilidadController extends Controller
{
    public function planCuentas(): JsonResponse
    {
        $cuentas = DB::table('plan_cuentas')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('activa', true)
            ->orderBy('codigo')
            ->get();

        return response()->json($cuentas);
    }

    public function crearCuenta(Request $request): JsonResponse
    {
        $request->validate([
            'codigo' => 'required|string|max:20',
            'nombre' => 'required|string|max:150',
            'tipo' => 'required|in:activo,pasivo,patrimonio,ingreso,gasto,resultado',
            'naturaleza' => 'required|in:deudora,acreedora',
        ]);

        $id = DB::table('plan_cuentas')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'codigo' => $request->codigo,
            'nombre' => $request->nombre,
            'tipo' => $request->tipo,
            'naturaleza' => $request->naturaleza,
            'cuenta_padre_id' => $request->cuenta_padre_id,
            'nivel' => $request->nivel ?? 1,
            'permite_movimientos' => $request->permite_movimientos ?? true,
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Cuenta creada', 'id' => $id], 201);
    }

    public function asientos(Request $request): JsonResponse
    {
        $asientos = DB::table('asientos')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->fecha_desde, fn($q) => $q->where('fecha', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn($q) => $q->where('fecha', '<=', $request->fecha_hasta))
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
            ->orderByDesc('fecha')
            ->orderByDesc('numero')
            ->paginate(50);

        return response()->json($asientos);
    }

    public function crearAsiento(Request $request): JsonResponse
    {
        $request->validate([
            'fecha' => 'required|date',
            'glosa' => 'required|string|max:500',
            'lineas' => 'required|array|min:2',
            'lineas.*.cuenta_id' => 'required|exists:plan_cuentas,id',
            'lineas.*.debe' => 'required|numeric|min:0',
            'lineas.*.haber' => 'required|numeric|min:0',
        ]);

        $totalDebe = collect($request->lineas)->sum('debe');
        $totalHaber = collect($request->lineas)->sum('haber');

        if (abs($totalDebe - $totalHaber) > 1) {
            return response()->json(['message' => 'El asiento no está cuadrado'], 422);
        }

        $numero = DB::table('asientos')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereYear('fecha', Carbon::parse($request->fecha)->year)
            ->max('numero') + 1;

        $asientoId = DB::table('asientos')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'numero' => str_pad($numero, 6, '0', STR_PAD_LEFT),
            'fecha' => $request->fecha,
            'tipo' => $request->tipo ?? 'traspaso',
            'glosa' => $request->glosa,
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'estado' => 'borrador',
            'creado_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($request->lineas as $orden => $linea) {
            DB::table('asiento_lineas')->insert([
                'asiento_id' => $asientoId,
                'cuenta_id' => $linea['cuenta_id'],
                'glosa' => $linea['glosa'] ?? null,
                'debe' => $linea['debe'],
                'haber' => $linea['haber'],
                'orden' => $orden + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Asiento creado', 'id' => $asientoId], 201);
    }

    public function showAsiento(int $id): JsonResponse
    {
        $asiento = DB::table('asientos')->where('id', $id)->first();

        if (!$asiento) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        $asiento->lineas = DB::table('asiento_lineas')
            ->join('plan_cuentas', 'asiento_lineas.cuenta_id', '=', 'plan_cuentas.id')
            ->where('asiento_lineas.asiento_id', $id)
            ->select('asiento_lineas.*', 'plan_cuentas.codigo', 'plan_cuentas.nombre as cuenta')
            ->orderBy('orden')
            ->get();

        return response()->json($asiento);
    }

    public function libroDiario(Request $request): JsonResponse
    {
        $asientos = DB::table('asientos')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('estado', 'contabilizado')
            ->when($request->fecha_desde, fn($q) => $q->where('fecha', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn($q) => $q->where('fecha', '<=', $request->fecha_hasta))
            ->orderBy('fecha')
            ->orderBy('numero')
            ->get();

        foreach ($asientos as $asiento) {
            $asiento->lineas = DB::table('asiento_lineas')
                ->join('plan_cuentas', 'asiento_lineas.cuenta_id', '=', 'plan_cuentas.id')
                ->where('asiento_lineas.asiento_id', $asiento->id)
                ->select('plan_cuentas.codigo', 'plan_cuentas.nombre', 'asiento_lineas.debe', 'asiento_lineas.haber')
                ->get();
        }

        return response()->json($asientos);
    }

    public function libroMayor(Request $request): JsonResponse
    {
        $cuentaId = $request->get('cuenta_id');

        $movimientos = DB::table('asiento_lineas')
            ->join('asientos', 'asiento_lineas.asiento_id', '=', 'asientos.id')
            ->join('plan_cuentas', 'asiento_lineas.cuenta_id', '=', 'plan_cuentas.id')
            ->where('asientos.tenant_id', Auth::user()->tenant_id)
            ->where('asientos.estado', 'contabilizado')
            ->when($cuentaId, fn($q) => $q->where('asiento_lineas.cuenta_id', $cuentaId))
            ->when($request->fecha_desde, fn($q) => $q->where('asientos.fecha', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn($q) => $q->where('asientos.fecha', '<=', $request->fecha_hasta))
            ->select(
                'asientos.fecha', 'asientos.numero', 'asientos.glosa',
                'plan_cuentas.codigo', 'plan_cuentas.nombre as cuenta',
                'asiento_lineas.debe', 'asiento_lineas.haber'
            )
            ->orderBy('asientos.fecha')
            ->get();

        return response()->json($movimientos);
    }

    public function balance(Request $request): JsonResponse
    {
        $saldos = DB::table('asiento_lineas')
            ->join('asientos', 'asiento_lineas.asiento_id', '=', 'asientos.id')
            ->join('plan_cuentas', 'asiento_lineas.cuenta_id', '=', 'plan_cuentas.id')
            ->where('asientos.tenant_id', Auth::user()->tenant_id)
            ->where('asientos.estado', 'contabilizado')
            ->when($request->fecha_hasta, fn($q) => $q->where('asientos.fecha', '<=', $request->fecha_hasta))
            ->select(
                'plan_cuentas.codigo', 'plan_cuentas.nombre', 'plan_cuentas.tipo', 'plan_cuentas.naturaleza',
                DB::raw('SUM(asiento_lineas.debe) as total_debe'),
                DB::raw('SUM(asiento_lineas.haber) as total_haber')
            )
            ->groupBy('plan_cuentas.id', 'plan_cuentas.codigo', 'plan_cuentas.nombre', 'plan_cuentas.tipo', 'plan_cuentas.naturaleza')
            ->orderBy('plan_cuentas.codigo')
            ->get();

        foreach ($saldos as $cuenta) {
            $cuenta->saldo = $cuenta->naturaleza === 'deudora'
                ? $cuenta->total_debe - $cuenta->total_haber
                : $cuenta->total_haber - $cuenta->total_debe;
        }

        return response()->json($saldos);
    }
}
