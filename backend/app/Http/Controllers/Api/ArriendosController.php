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
