<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

// ========================================
// GASTOS COMUNES CONTROLLER
// ========================================
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
class ArriendosController extends Controller
{
    public function contratos(Request $request): JsonResponse
    {
        $contratos = DB::table('contratos_arriendo')
            ->join('edificios', 'contratos_arriendo.edificio_id', '=', 'edificios.id')
            ->join('arrendatarios', 'contratos_arriendo.arrendatario_id', '=', 'arrendatarios.id')
            ->where('contratos_arriendo.tenant_id', Auth::user()->tenant_id)
            ->whereNull('contratos_arriendo.deleted_at')
            ->when($request->edificio_id, fn($q) => $q->where('contratos_arriendo.edificio_id', $request->edificio_id))
            ->when($request->estado, fn($q) => $q->where('contratos_arriendo.estado', $request->estado))
            ->select('contratos_arriendo.*', 'edificios.nombre as edificio', 'arrendatarios.razon_social as arrendatario')
            ->orderByDesc('contratos_arriendo.fecha_inicio')
            ->get();

        return response()->json($contratos);
    }

    public function crearContrato(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'arrendatario_id' => 'required|exists:arrendatarios,id',
            'tipo_espacio' => 'required|in:azotea,fachada,subterraneo,terreno,sala_tecnica,otro',
            'ubicacion_espacio' => 'required|string|max:200',
            'fecha_inicio' => 'required|date',
            'fecha_termino' => 'required|date|after:fecha_inicio',
            'monto_mensual' => 'required|numeric|min:0',
            'moneda' => 'required|in:CLP,UF,USD',
        ]);

        $id = DB::table('contratos_arriendo')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'arrendatario_id' => $request->arrendatario_id,
            'tipo_espacio' => $request->tipo_espacio,
            'ubicacion_espacio' => $request->ubicacion_espacio,
            'superficie_m2' => $request->superficie_m2,
            'descripcion_espacio' => $request->descripcion_espacio,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_termino' => $request->fecha_termino,
            'monto_mensual' => $request->monto_mensual,
            'moneda' => $request->moneda,
            'dia_facturacion' => $request->dia_facturacion ?? 1,
            'dias_pago' => $request->dias_pago ?? 30,
            'reajuste_tipo' => $request->reajuste_tipo ?? 'uf',
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Contrato creado', 'id' => $id], 201);
    }

    public function showContrato(int $id): JsonResponse
    {
        $contrato = DB::table('contratos_arriendo')
            ->join('edificios', 'contratos_arriendo.edificio_id', '=', 'edificios.id')
            ->join('arrendatarios', 'contratos_arriendo.arrendatario_id', '=', 'arrendatarios.id')
            ->where('contratos_arriendo.id', $id)
            ->select('contratos_arriendo.*', 'edificios.nombre as edificio', 'arrendatarios.*')
            ->first();

        if (!$contrato) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        $contrato->facturas = DB::table('facturas_arriendo')
            ->where('contrato_id', $id)
            ->orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes')
            ->limit(12)
            ->get();

        return response()->json($contrato);
    }

    public function updateContrato(Request $request, int $id): JsonResponse
    {
        DB::table('contratos_arriendo')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(array_merge(
                $request->only(['monto_mensual', 'fecha_termino', 'estado', 'observaciones']),
                ['updated_at' => now()]
            ));

        return response()->json(['message' => 'Actualizado']);
    }

    public function facturas(Request $request): JsonResponse
    {
        $facturas = DB::table('facturas_arriendo')
            ->join('contratos_arriendo', 'facturas_arriendo.contrato_id', '=', 'contratos_arriendo.id')
            ->join('arrendatarios', 'contratos_arriendo.arrendatario_id', '=', 'arrendatarios.id')
            ->where('facturas_arriendo.tenant_id', Auth::user()->tenant_id)
            ->when($request->edificio_id, fn($q) => $q->where('facturas_arriendo.edificio_id', $request->edificio_id))
            ->when($request->estado, fn($q) => $q->where('facturas_arriendo.estado', $request->estado))
            ->select('facturas_arriendo.*', 'arrendatarios.razon_social as arrendatario')
            ->orderByDesc('facturas_arriendo.periodo_anio')
            ->orderByDesc('facturas_arriendo.periodo_mes')
            ->paginate(50);

        return response()->json($facturas);
    }

    public function generarFacturas(Request $request): JsonResponse
    {
        $request->validate([
            'mes' => 'required|integer|between:1,12',
            'anio' => 'required|integer|min:2020',
        ]);

        $contratos = DB::table('contratos_arriendo')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('estado', 'activo')
            ->when($request->edificio_id, fn($q) => $q->where('edificio_id', $request->edificio_id))
            ->get();

        $uf = DB::table('indicadores_economicos')
            ->where('codigo', 'UF')
            ->orderByDesc('fecha')
            ->value('valor') ?? 38500;

        $generadas = 0;
        foreach ($contratos as $contrato) {
            // Verificar si ya existe
            $existe = DB::table('facturas_arriendo')
                ->where('contrato_id', $contrato->id)
                ->where('periodo_mes', $request->mes)
                ->where('periodo_anio', $request->anio)
                ->exists();

            if ($existe) continue;

            // Calcular monto
            $montoNeto = $contrato->moneda === 'UF'
                ? round($contrato->monto_mensual * $uf, 0)
                : $contrato->monto_mensual;

            $iva = round($montoNeto * 0.19, 0);
            $montoTotal = $montoNeto + $iva;

            $fechaEmision = Carbon::create($request->anio, $request->mes, $contrato->dia_facturacion);
            $fechaVencimiento = $fechaEmision->copy()->addDays($contrato->dias_pago);

            DB::table('facturas_arriendo')->insert([
                'tenant_id' => $contrato->tenant_id,
                'edificio_id' => $contrato->edificio_id,
                'contrato_id' => $contrato->id,
                'periodo_mes' => $request->mes,
                'periodo_anio' => $request->anio,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'monto_neto' => $montoNeto,
                'iva' => $iva,
                'monto_total' => $montoTotal,
                'monto_uf' => $contrato->moneda === 'UF' ? $contrato->monto_mensual : null,
                'valor_uf_usado' => $uf,
                'estado' => 'emitida',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $generadas++;
        }

        return response()->json(['message' => "Se generaron {$generadas} facturas"]);
    }

    public function showFactura(int $id): JsonResponse
    {
        $factura = DB::table('facturas_arriendo')
            ->join('contratos_arriendo', 'facturas_arriendo.contrato_id', '=', 'contratos_arriendo.id')
            ->join('arrendatarios', 'contratos_arriendo.arrendatario_id', '=', 'arrendatarios.id')
            ->join('edificios', 'facturas_arriendo.edificio_id', '=', 'edificios.id')
            ->where('facturas_arriendo.id', $id)
            ->select('facturas_arriendo.*', 'arrendatarios.*', 'edificios.nombre as edificio', 'edificios.rut as edificio_rut')
            ->first();

        return $factura
            ? response()->json($factura)
            : response()->json(['message' => 'No encontrada'], 404);
    }

    public function facturaPdf(int $id)
    {
        $factura = DB::table('facturas_arriendo')
            ->join('contratos_arriendo', 'facturas_arriendo.contrato_id', '=', 'contratos_arriendo.id')
            ->join('arrendatarios', 'contratos_arriendo.arrendatario_id', '=', 'arrendatarios.id')
            ->join('edificios', 'facturas_arriendo.edificio_id', '=', 'edificios.id')
            ->where('facturas_arriendo.id', $id)
            ->first();

        $pdf = Pdf::loadView('pdf.factura-arriendo', compact('factura'));
        return $pdf->download("factura-{$factura->numero_factura}.pdf");
    }

    public function arrendatarios(): JsonResponse
    {
        $arrendatarios = DB::table('arrendatarios')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get();

        return response()->json($arrendatarios);
    }

    public function crearArrendatario(Request $request): JsonResponse
    {
        $request->validate([
            'rut' => 'required|string|max:12',
            'razon_social' => 'required|string|max:200',
            'email' => 'required|email',
            'direccion' => 'required|string|max:300',
        ]);

        $id = DB::table('arrendatarios')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'rut' => $request->rut,
            'razon_social' => $request->razon_social,
            'nombre_fantasia' => $request->nombre_fantasia,
            'giro' => $request->giro,
            'direccion' => $request->direccion,
            'comuna' => $request->comuna,
            'telefono' => $request->telefono,
            'email' => $request->email,
            'contacto_nombre' => $request->contacto_nombre,
            'contacto_email' => $request->contacto_email,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Arrendatario creado', 'id' => $id], 201);
    }
}

// ========================================
// DISTRIBUCIÓN CONTROLLER
// ========================================
class DistribucionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $distribuciones = DB::table('distribuciones')
            ->join('edificios', 'distribuciones.edificio_id', '=', 'edificios.id')
            ->where('distribuciones.tenant_id', Auth::user()->tenant_id)
            ->when($request->edificio_id, fn($q) => $q->where('distribuciones.edificio_id', $request->edificio_id))
            ->select('distribuciones.*', 'edificios.nombre as edificio')
            ->orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes')
            ->paginate(24);

        return response()->json($distribuciones);
    }

    public function crear(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'factura_id' => 'required|exists:facturas_arriendo,id',
            'periodo_mes' => 'required|integer|between:1,12',
            'periodo_anio' => 'required|integer|min:2020',
            'concepto' => 'required|string|max:200',
        ]);

        $factura = DB::table('facturas_arriendo')->where('id', $request->factura_id)->first();

        $id = DB::table('distribuciones')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'contrato_id' => $factura->contrato_id,
            'factura_id' => $request->factura_id,
            'periodo_mes' => $request->periodo_mes,
            'periodo_anio' => $request->periodo_anio,
            'concepto' => $request->concepto,
            'monto_bruto' => $factura->monto_neto,
            'gastos_administracion' => $request->gastos_administracion ?? 0,
            'porcentaje_administracion' => $request->porcentaje_administracion ?? 0,
            'monto_neto' => $factura->monto_neto - ($request->gastos_administracion ?? 0),
            'metodo_distribucion' => $request->metodo ?? 'prorrateo',
            'estado' => 'borrador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Distribución creada', 'id' => $id], 201);
    }

    public function show(int $id): JsonResponse
    {
        $distribucion = DB::table('distribuciones')
            ->join('edificios', 'distribuciones.edificio_id', '=', 'edificios.id')
            ->where('distribuciones.id', $id)
            ->select('distribuciones.*', 'edificios.nombre as edificio')
            ->first();

        if (!$distribucion) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        $distribucion->detalle = DB::table('distribucion_detalle')
            ->join('unidades', 'distribucion_detalle.unidad_id', '=', 'unidades.id')
            ->join('personas', 'distribucion_detalle.beneficiario_id', '=', 'personas.id')
            ->where('distribucion_detalle.distribucion_id', $id)
            ->select('distribucion_detalle.*', 'unidades.numero', 'personas.nombre_completo', 'personas.rut')
            ->get();

        return response()->json($distribucion);
    }

    public function procesar(int $id): JsonResponse
    {
        $distribucion = DB::table('distribuciones')->where('id', $id)->first();

        if (!$distribucion || $distribucion->estado !== 'borrador') {
            return response()->json(['message' => 'No se puede procesar'], 422);
        }

        $unidades = DB::table('unidades')
            ->join('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->where('unidades.edificio_id', $distribucion->edificio_id)
            ->where('unidades.activa', true)
            ->whereNotNull('unidades.propietario_id')
            ->select('unidades.*', 'personas.id as persona_id')
            ->get();

        $totalProrrateo = $unidades->sum('prorrateo');
        $beneficiarios = 0;

        foreach ($unidades as $unidad) {
            $porcentaje = $totalProrrateo > 0 ? ($unidad->prorrateo / $totalProrrateo) : 0;
            $montoBruto = round($distribucion->monto_neto * $porcentaje, 0);

            if ($montoBruto <= 0) continue;

            // Art. 17 N°3 Ley Renta: no tributa si es proporcional a derechos
            $retencion = 0;

            DB::table('distribucion_detalle')->insert([
                'distribucion_id' => $id,
                'unidad_id' => $unidad->id,
                'beneficiario_id' => $unidad->persona_id,
                'porcentaje_participacion' => $porcentaje * 100,
                'monto_bruto' => $montoBruto,
                'retencion_impuesto' => $retencion,
                'monto_neto' => $montoBruto - $retencion,
                'forma_pago' => 'descuento_gc',
                'pagado' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $beneficiarios++;
        }

        DB::table('distribuciones')->where('id', $id)->update([
            'estado' => 'procesada',
            'total_beneficiarios' => $beneficiarios,
            'procesada_por' => Auth::id(),
            'procesada_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => "Distribución procesada para {$beneficiarios} beneficiarios"]);
    }

    public function aprobar(int $id): JsonResponse
    {
        DB::table('distribuciones')->where('id', $id)->update([
            'estado' => 'aprobada',
            'aprobado_por' => Auth::id(),
            'aprobada_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Distribución aprobada']);
    }

    public function detalle(int $id): JsonResponse
    {
        $detalle = DB::table('distribucion_detalle')
            ->join('unidades', 'distribucion_detalle.unidad_id', '=', 'unidades.id')
            ->join('personas', 'distribucion_detalle.beneficiario_id', '=', 'personas.id')
            ->where('distribucion_detalle.distribucion_id', $id)
            ->select('distribucion_detalle.*', 'unidades.numero', 'personas.nombre_completo', 'personas.rut', 'personas.email')
            ->orderBy('unidades.numero')
            ->get();

        return response()->json($detalle);
    }

    public function certificados(Request $request): JsonResponse
    {
        $certificados = DB::table('certificados_renta')
            ->join('unidades', 'certificados_renta.unidad_id', '=', 'unidades.id')
            ->join('personas', 'certificados_renta.beneficiario_id', '=', 'personas.id')
            ->where('certificados_renta.tenant_id', Auth::user()->tenant_id)
            ->when($request->anio, fn($q) => $q->where('certificados_renta.anio', $request->anio))
            ->select('certificados_renta.*', 'unidades.numero', 'personas.nombre_completo', 'personas.rut')
            ->orderByDesc('anio')
            ->paginate(50);

        return response()->json($certificados);
    }

    public function generarCertificadosMasivo(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'anio' => 'required|integer|min:2020',
        ]);

        // Obtener totales por unidad del año
        $totales = DB::table('distribucion_detalle')
            ->join('distribuciones', 'distribucion_detalle.distribucion_id', '=', 'distribuciones.id')
            ->where('distribuciones.edificio_id', $request->edificio_id)
            ->where('distribuciones.periodo_anio', $request->anio)
            ->where('distribuciones.estado', 'aprobada')
            ->select(
                'distribucion_detalle.unidad_id',
                'distribucion_detalle.beneficiario_id',
                DB::raw('SUM(distribucion_detalle.monto_neto) as renta_total')
            )
            ->groupBy('distribucion_detalle.unidad_id', 'distribucion_detalle.beneficiario_id')
            ->get();

        $generados = 0;
        foreach ($totales as $total) {
            // Verificar si ya existe
            $existe = DB::table('certificados_renta')
                ->where('unidad_id', $total->unidad_id)
                ->where('anio', $request->anio)
                ->exists();

            if ($existe) continue;

            $numeroCertificado = sprintf('CR-%d-%04d', $request->anio, $total->unidad_id);
            $codigoVerificacion = strtoupper(substr(md5($numeroCertificado . now()), 0, 10));

            DB::table('certificados_renta')->insert([
                'tenant_id' => Auth::user()->tenant_id,
                'edificio_id' => $request->edificio_id,
                'unidad_id' => $total->unidad_id,
                'beneficiario_id' => $total->beneficiario_id,
                'anio' => $request->anio,
                'numero_certificado' => $numeroCertificado,
                'fecha_emision' => now(),
                'renta_total' => $total->renta_total,
                'renta_articulo_17' => $total->renta_total, // Art. 17 N°3
                'renta_articulo_20' => 0,
                'retenciones' => 0,
                'tipo_certificado' => 'art_17',
                'codigo_verificacion' => $codigoVerificacion,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $generados++;
        }

        return response()->json(['message' => "Se generaron {$generados} certificados"]);
    }

    public function certificadoPdf(int $id)
    {
        $certificado = DB::table('certificados_renta')
            ->join('unidades', 'certificados_renta.unidad_id', '=', 'unidades.id')
            ->join('personas', 'certificados_renta.beneficiario_id', '=', 'personas.id')
            ->join('edificios', 'certificados_renta.edificio_id', '=', 'edificios.id')
            ->where('certificados_renta.id', $id)
            ->first();

        $pdf = Pdf::loadView('pdf.certificado-renta', compact('certificado'));
        return $pdf->download("certificado-{$certificado->numero_certificado}.pdf");
    }
}
