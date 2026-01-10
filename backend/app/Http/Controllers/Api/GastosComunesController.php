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

class GastosComunesController extends Controller
{
    public function periodos(Request $request): JsonResponse
    {
        $periodos = DB::table('periodos_gc')
            ->join('edificios', 'periodos_gc.edificio_id', '=', 'edificios.id')
            ->where('periodos_gc.tenant_id', Auth::user()->tenant_id)
            ->when($request->edificio_id, fn($q) => $q->where('periodos_gc.edificio_id', $request->edificio_id))
            ->select('periodos_gc.*', 'edificios.nombre as edificio')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->paginate(24);

        return response()->json($periodos);
    }

    public function crearPeriodo(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'mes' => 'required|integer|between:1,12',
            'anio' => 'required|integer|min:2020',
            'fecha_vencimiento' => 'required|date',
        ]);

        $existe = DB::table('periodos_gc')
            ->where('edificio_id', $request->edificio_id)
            ->where('mes', $request->mes)
            ->where('anio', $request->anio)
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'El período ya existe'], 422);
        }

        $id = DB::table('periodos_gc')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'mes' => $request->mes,
            'anio' => $request->anio,
            'fecha_emision' => now(),
            'fecha_vencimiento' => $request->fecha_vencimiento,
            'estado' => 'abierto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Período creado', 'id' => $id], 201);
    }

    public function showPeriodo(int $id): JsonResponse
    {
        $periodo = DB::table('periodos_gc')
            ->join('edificios', 'periodos_gc.edificio_id', '=', 'edificios.id')
            ->where('periodos_gc.id', $id)
            ->select('periodos_gc.*', 'edificios.nombre as edificio')
            ->first();

        if (!$periodo) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        $boletas = DB::table('boletas_gc')
            ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
            ->where('boletas_gc.periodo_id', $id)
            ->select('boletas_gc.*', 'unidades.numero')
            ->orderBy('unidades.numero')
            ->get();

    $boletasPendientes = DB::table('boletas_gc')
    ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
    ->where('boletas_gc.unidad_id', $request->unidad_id)
    ->whereIn('boletas_gc.estado', ['pendiente', 'parcial'])
    ->where('boletas_gc.fecha_vencimiento', '<=', $fechaCorte)
    ->selectRaw('SUM(boletas_gc.total_a_pagar - COALESCE(boletas_gc.total_abonos, 0)) as deuda_gc, SUM(boletas_gc.total_fondo_reserva) as deuda_fondo, SUM(boletas_gc.total_intereses) as deuda_intereses
    ')
    ->first();

// NOTA: Verificar schema de tabla boletas_gc. Si el campo es 'total_pagado' en lugar de 'total_abonos', cambiar TODAS las referencias

    public function generarBoletas(int $periodoId): JsonResponse
    {
        $periodo = DB::table('periodos_gc')->where('id', $periodoId)->first();
        if (!$periodo || $periodo->estado !== 'abierto') {
            return response()->json(['message' => 'Período no válido'], 422);
        }

        $unidades = DB::table('unidades')
            ->where('edificio_id', $periodo->edificio_id)
            ->where('activa', true)
            ->get();

        $edificio = DB::table('edificios')->where('id', $periodo->edificio_id)->first();
        $conceptos = DB::table('conceptos_gc')
            ->where('tenant_id', $periodo->tenant_id)
            ->where('activo', true)
            ->get();

        $presupuesto = DB::table('presupuestos_gc')
            ->where('edificio_id', $periodo->edificio_id)
            ->where('anio', $periodo->anio)
            ->get()
            ->keyBy('concepto_id');

        $generadas = 0;
        foreach ($unidades as $unidad) {
            // Verificar si ya existe
            $existe = DB::table('boletas_gc')
                ->where('periodo_id', $periodoId)
                ->where('unidad_id', $unidad->id)
                ->exists();

            if ($existe) continue;

            // Calcular saldo anterior
            $saldoAnterior = DB::table('boletas_gc')
                ->where('unidad_id', $unidad->id)
                ->where('periodo_id', '<', $periodoId)
                ->whereIn('estado', ['pendiente', 'vencida'])
                ->sum(DB::raw('total_a_pagar - COALESCE(total_abonos, 0)'));

            // Calcular cargos
            $totalCargos = 0;
            $cargosData = [];

            foreach ($conceptos as $concepto) {
                $montoBase = $presupuesto[$concepto->id]->monto_mensual ?? 0;
                $monto = $concepto->metodo_calculo === 'prorrateo'
                    ? round($montoBase * ($unidad->prorrateo / 100), 0)
                    : $montoBase;

                if ($monto > 0) {
                    $cargosData[] = [
                        'concepto_id' => $concepto->id,
                        'descripcion' => $concepto->nombre,
                        'monto' => $monto,
                        'tipo' => $concepto->tipo,
                    ];
                    $totalCargos += $monto;
                }
            }

            $numeroBoleta = sprintf('%s-%04d-%02d-%04d',
                $edificio->rut,
                $periodo->anio,
                $periodo->mes,
                $unidad->id
            );

            $boletaId = DB::table('boletas_gc')->insertGetId([
                'tenant_id' => $periodo->tenant_id,
                'edificio_id' => $periodo->edificio_id,
                'periodo_id' => $periodoId,
                'unidad_id' => $unidad->id,
                'numero_boleta' => $numeroBoleta,
                'fecha_emision' => $periodo->fecha_emision ?? now(),
                'fecha_vencimiento' => $periodo->fecha_vencimiento,
                'saldo_anterior' => $saldoAnterior,
                'total_cargos' => $totalCargos,
                'total_abonos' => 0,
                'total_intereses' => 0,
                'total_a_pagar' => $saldoAnterior + $totalCargos,
                'estado' => 'pendiente',
                'dias_mora' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insertar cargos
            foreach ($cargosData as $cargo) {
                DB::table('cargos_gc')->insert([
                    'boleta_id' => $boletaId,
                    'concepto_id' => $cargo['concepto_id'],
                    'descripcion' => $cargo['descripcion'],
                    'monto' => $cargo['monto'],
                    'tipo' => $cargo['tipo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $generadas++;
        }

        // Actualizar totales del período
        $totales = DB::table('boletas_gc')
            ->where('periodo_id', $periodoId)
            ->selectRaw('SUM(total_a_pagar) as emitido, SUM(total_abonos) as recaudado')
            ->first();

        DB::table('periodos_gc')->where('id', $periodoId)->update([
            'total_emitido' => $totales->emitido ?? 0,
            'total_recaudado' => $totales->recaudado ?? 0,
            'total_pendiente' => ($totales->emitido ?? 0) - ($totales->recaudado ?? 0),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => "Se generaron {$generadas} boletas"]);
    }

    public function cerrarPeriodo(int $periodoId): JsonResponse
    {
        DB::table('periodos_gc')->where('id', $periodoId)->update([
            'estado' => 'cerrado',
            'cerrado_at' => now(),
            'cerrado_por' => Auth::id(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Período cerrado']);
    }

    public function boletas(Request $request): JsonResponse
    {
        $query = DB::table('boletas_gc')
            ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->leftJoin('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->where('boletas_gc.tenant_id', Auth::user()->tenant_id)
            ->select(
                'boletas_gc.*',
                'unidades.numero as unidad',
                'periodos_gc.mes', 'periodos_gc.anio',
                'personas.nombre_completo as propietario'
            );

        if ($request->edificio_id) {
            $query->where('boletas_gc.edificio_id', $request->edificio_id);
        }

        if ($request->estado) {
            $query->where('boletas_gc.estado', $request->estado);
        }

        return response()->json($query->orderByDesc('periodos_gc.anio')->orderByDesc('periodos_gc.mes')->paginate(50));
    }

    public function showBoleta(int $id): JsonResponse
    {
        $boleta = DB::table('boletas_gc')
            ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->join('edificios', 'boletas_gc.edificio_id', '=', 'edificios.id')
            ->leftJoin('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->where('boletas_gc.id', $id)
            ->select(
                'boletas_gc.*',
                'unidades.numero as unidad', 'unidades.tipo as tipo_unidad',
                'periodos_gc.mes', 'periodos_gc.anio',
                'edificios.nombre as edificio', 'edificios.direccion as edificio_direccion',
                'personas.nombre_completo as propietario', 'personas.rut as propietario_rut'
            )
            ->first();

        if (!$boleta) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        $boleta->cargos = DB::table('cargos_gc')->where('boleta_id', $id)->get();
        $boleta->pagos = DB::table('pagos_gc')->where('boleta_id', $id)->whereNull('deleted_at')->get();

        return response()->json($boleta);
    }

    public function boletaPdf(int $id)
    {
        $boleta = DB::table('boletas_gc')
            ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->join('edificios', 'boletas_gc.edificio_id', '=', 'edificios.id')
            ->leftJoin('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->where('boletas_gc.id', $id)
            ->select('boletas_gc.*', 'unidades.numero as unidad', 'periodos_gc.mes', 'periodos_gc.anio',
                     'edificios.nombre as edificio', 'edificios.direccion', 'edificios.rut as edificio_rut',
                     'personas.nombre_completo as propietario', 'personas.rut as propietario_rut')
            ->first();

        $cargos = DB::table('cargos_gc')->where('boleta_id', $id)->get();

        $pdf = Pdf::loadView('pdf.boleta-gc', compact('boleta', 'cargos'));
        return $pdf->download("boleta-{$boleta->numero_boleta}.pdf");
    }

    public function pagos(Request $request): JsonResponse
    {
        $pagos = DB::table('pagos_gc')
            ->join('boletas_gc', 'pagos_gc.boleta_id', '=', 'boletas_gc.id')
            ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
            ->where('pagos_gc.tenant_id', Auth::user()->tenant_id)
            ->whereNull('pagos_gc.deleted_at')
            ->when($request->edificio_id, fn($q) => $q->where('boletas_gc.edificio_id', $request->edificio_id))
            ->select('pagos_gc.*', 'unidades.numero as unidad', 'boletas_gc.numero_boleta')
            ->orderByDesc('pagos_gc.fecha_pago')
            ->paginate(50);

        return response()->json($pagos);
    }

    public function registrarPago(Request $request): JsonResponse
    {
        $request->validate([
            'boleta_id' => 'required|exists:boletas_gc,id',
            'monto' => 'required|numeric|min:1',
            'fecha_pago' => 'required|date',
            'medio_pago' => 'required|in:efectivo,transferencia,cheque,tarjeta,pac,webpay,otro',
        ]);

        $boleta = DB::table('boletas_gc')->where('id', $request->boleta_id)->first();
        $saldoPendiente = $boleta->total_a_pagar - $boleta->total_abonos;

        if ($request->monto > $saldoPendiente) {
            return response()->json(['message' => 'El monto excede el saldo pendiente'], 422);
        }

        $pagoId = DB::table('pagos_gc')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'boleta_id' => $request->boleta_id,
            'monto' => $request->monto,
            'fecha_pago' => $request->fecha_pago,
            'medio_pago' => $request->medio_pago,
            'referencia' => $request->referencia,
            'banco' => $request->banco,
            'numero_operacion' => $request->numero_operacion,
            'observaciones' => $request->observaciones,
            'estado' => 'confirmado',
            'registrado_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actualizar boleta
        $nuevoAbono = $boleta->total_abonos + $request->monto;
        $nuevoEstado = $nuevoAbono >= $boleta->total_a_pagar ? 'pagada' : 'parcial';

        DB::table('boletas_gc')->where('id', $request->boleta_id)->update([
            'total_abonos' => $nuevoAbono,
            'estado' => $nuevoEstado,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Pago registrado', 'id' => $pagoId], 201);
    }

    public function morosidad(Request $request): JsonResponse
    {
        $morosos = DB::table('boletas_gc')
            ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
            ->join('edificios', 'boletas_gc.edificio_id', '=', 'edificios.id')
            ->leftJoin('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->where('boletas_gc.tenant_id', Auth::user()->tenant_id)
            ->whereIn('boletas_gc.estado', ['pendiente', 'vencida'])
            ->when($request->edificio_id, fn($q) => $q->where('boletas_gc.edificio_id', $request->edificio_id))
            ->select(
                'unidades.id as unidad_id', 'unidades.numero',
                'edificios.nombre as edificio',
                'personas.nombre_completo as propietario',
                'personas.email', 'personas.telefono',
                DB::raw('SUM(boletas_gc.total_a_pagar - COALESCE(boletas_gc.total_abonos, 0)) as deuda'),
                DB::raw('COUNT(boletas_gc.id) as boletas_pendientes'),
                DB::raw('MAX(boletas_gc.dias_mora) as dias_mora')
            )
            ->groupBy('unidades.id', 'unidades.numero', 'edificios.nombre', 'personas.nombre_completo', 'personas.email', 'personas.telefono')
            ->havingRaw('SUM(boletas_gc.total_a_pagar - COALESCE(boletas_gc.total_abonos, 0)) > 0')
            ->orderByDesc('deuda')
            ->get();

        $totales = [
            'total_morosos' => $morosos->count(),
            'deuda_total' => $morosos->sum('deuda'),
        ];

        return response()->json(['totales' => $totales, 'morosos' => $morosos]);
    }

    public function conceptos(): JsonResponse
    {
        $conceptos = DB::table('conceptos_gc')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        return response()->json($conceptos);
    }
}

// ========================================
// ARRIENDOS CONTROLLER
// ========================================
