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
