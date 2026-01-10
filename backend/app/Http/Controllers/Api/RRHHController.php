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

class RRHHController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empleados = DB::table('empleados')
            ->leftJoin('cargos', 'empleados.cargo_id', '=', 'cargos.id')
            ->where('empleados.tenant_id', Auth::user()->tenant_id)
            ->whereNull('empleados.deleted_at')
            ->when($request->estado, fn($q) => $q->where('empleados.estado', $request->estado))
            ->select('empleados.*', 'cargos.nombre as cargo')
            ->orderBy('empleados.apellido_paterno')
            ->get();

        return response()->json($empleados);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'rut' => 'required|string|max:12',
            'nombres' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'fecha_nacimiento' => 'required|date',
            'fecha_ingreso' => 'required|date',
            'sueldo_base' => 'required|numeric|min:0',
            'tipo_contrato' => 'required|in:indefinido,plazo_fijo,por_obra,honorarios',
        ]);

        $id = DB::table('empleados')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'rut' => $request->rut,
            'nombres' => $request->nombres,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'sexo' => $request->sexo,
            'direccion' => $request->direccion,
            'comuna' => $request->comuna,
            'telefono' => $request->telefono,
            'email' => $request->email,
            'cargo_id' => $request->cargo_id,
            'fecha_ingreso' => $request->fecha_ingreso,
            'tipo_contrato' => $request->tipo_contrato,
            'jornada' => $request->jornada ?? 'completa',
            'sueldo_base' => $request->sueldo_base,
            'gratificacion' => $request->gratificacion ?? 0,
            'colacion' => $request->colacion ?? 0,
            'movilizacion' => $request->movilizacion ?? 0,
            'afp_id' => $request->afp_id,
            'salud_id' => $request->salud_id,
            'banco_id' => $request->banco_id,
            'tipo_cuenta' => $request->tipo_cuenta,
            'numero_cuenta' => $request->numero_cuenta,
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Empleado creado', 'id' => $id], 201);
    }

    public function show(int $id): JsonResponse
    {
        $empleado = DB::table('empleados')
            ->leftJoin('cargos', 'empleados.cargo_id', '=', 'cargos.id')
            ->leftJoin('afp', 'empleados.afp_id', '=', 'afp.id')
            ->leftJoin('isapres', 'empleados.salud_id', '=', 'isapres.id')
            ->leftJoin('bancos', 'empleados.banco_id', '=', 'bancos.id')
            ->where('empleados.id', $id)
            ->select('empleados.*', 'cargos.nombre as cargo', 'afp.nombre as afp_nombre',
                     'afp.tasa_trabajador as afp_tasa', 'isapres.nombre as salud_nombre', 'bancos.nombre as banco_nombre')
            ->first();

        return $empleado
            ? response()->json($empleado)
            : response()->json(['message' => 'No encontrado'], 404);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        DB::table('empleados')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(array_merge(
                $request->only(['direccion', 'telefono', 'email', 'sueldo_base', 'cargo_id', 'afp_id', 'salud_id']),
                ['updated_at' => now()]
            ));

        return response()->json(['message' => 'Actualizado']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('empleados')->where('id', $id)->update([
            'deleted_at' => now(),
            'estado' => 'desvinculado',
        ]);

        return response()->json(['message' => 'Empleado desvinculado']);
    }

    public function liquidaciones(Request $request): JsonResponse
    {
        $liquidaciones = DB::table('liquidaciones')
            ->join('empleados', 'liquidaciones.empleado_id', '=', 'empleados.id')
            ->where('liquidaciones.tenant_id', Auth::user()->tenant_id)
            ->when($request->mes && $request->anio, function($q) use ($request) {
                $q->where('liquidaciones.mes', $request->mes)->where('liquidaciones.anio', $request->anio);
            })
            ->select('liquidaciones.*', 'empleados.rut', DB::raw("CONCAT(empleados.nombres, ' ', empleados.apellido_paterno) as empleado"))
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->paginate(50);

        return response()->json($liquidaciones);
    }

    public function liquidacionesEmpleado(int $empleadoId): JsonResponse
    {
        $liquidaciones = DB::table('liquidaciones')
            ->where('empleado_id', $empleadoId)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->limit(24)
            ->get();

        return response()->json($liquidaciones);
    }

    public function generarLiquidacion(Request $request): JsonResponse
    {
        $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'mes' => 'required|integer|between:1,12',
            'anio' => 'required|integer|min:2020',
        ]);

        $empleado = DB::table('empleados')
            ->leftJoin('afp', 'empleados.afp_id', '=', 'afp.id')
            ->leftJoin('isapres', 'empleados.salud_id', '=', 'isapres.id')
            ->where('empleados.id', $request->empleado_id)
            ->select('empleados.*', 'afp.tasa_trabajador as afp_tasa', 'isapres.tipo as salud_tipo')
            ->first();

        // Obtener indicadores
        $uf = DB::table('indicadores_economicos')->where('codigo', 'UF')->orderByDesc('fecha')->value('valor') ?? 38500;
        $utm = DB::table('indicadores_economicos')->where('codigo', 'UTM')->orderByDesc('fecha')->value('valor') ?? 67500;
        $topeImponible = 81.6 * $uf;

        // Calcular haberes
        $sueldoBase = $empleado->sueldo_base;
        $gratificacion = min($empleado->gratificacion, 4.75 * $utm / 12);
        $colacion = $empleado->colacion;
        $movilizacion = $empleado->movilizacion;

        $totalHaberes = $sueldoBase + $gratificacion + $colacion + $movilizacion;
        $totalImponible = min($sueldoBase + $gratificacion, $topeImponible);
        $totalTributable = $sueldoBase + $gratificacion;

        // Calcular descuentos
        $afpMonto = round($totalImponible * ($empleado->afp_tasa ?? 11.5) / 100, 0);
        $saludMonto = $empleado->salud_tipo === 'fonasa'
            ? round($totalImponible * 0.07, 0)
            : round($totalImponible * 0.07, 0); // O UF pactadas
        $cesantia = $empleado->afc ? round($totalImponible * 0.006, 0) : 0;

        // Impuesto único
        $baseImpuesto = $totalTributable - $afpMonto - $saludMonto - $cesantia;
        $impuesto = $this->calcularImpuesto($baseImpuesto, $utm);

        $totalDescuentosLegales = $afpMonto + $saludMonto + $cesantia + $impuesto;
        $totalDescuentos = $totalDescuentosLegales;
        $sueldoLiquido = $totalHaberes - $totalDescuentos;

        $id = DB::table('liquidaciones')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'empleado_id' => $request->empleado_id,
            'mes' => $request->mes,
            'anio' => $request->anio,
            'dias_trabajados' => 30,
            'sueldo_base' => $sueldoBase,
            'gratificacion' => $gratificacion,
            'asignacion_colacion' => $colacion,
            'asignacion_movilizacion' => $movilizacion,
            'total_haberes' => $totalHaberes,
            'total_imponible' => $totalImponible,
            'total_tributable' => $totalTributable,
            'afp' => $afpMonto,
            'afp_tasa' => $empleado->afp_tasa ?? 11.5,
            'salud' => $saludMonto,
            'salud_tasa' => 7,
            'seguro_cesantia' => $cesantia,
            'impuesto_unico' => $impuesto,
            'total_descuentos_legales' => $totalDescuentosLegales,
            'total_descuentos' => $totalDescuentos,
            'sueldo_liquido' => $sueldoLiquido,
            'uf_valor' => $uf,
            'utm_valor' => $utm,
            'tope_imponible' => $topeImponible,
            'estado' => 'borrador',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Liquidación generada', 'id' => $id], 201);
    }

    private function calcularImpuesto(float $base, float $utm): float
    {
        $tramos = DB::table('tramos_impuesto')
            ->where('anio', now()->year)
            ->orderBy('tramo')
            ->get();

        $baseUtm = $base / $utm;

        foreach ($tramos as $tramo) {
            if ($baseUtm >= $tramo->desde_utm && ($tramo->hasta_utm === null || $baseUtm < $tramo->hasta_utm)) {
                return round(($base * $tramo->factor) - ($tramo->rebaja_utm * $utm), 0);
            }
        }

        return 0;
    }

    public function showLiquidacion(int $id): JsonResponse
    {
        $liquidacion = DB::table('liquidaciones')
            ->join('empleados', 'liquidaciones.empleado_id', '=', 'empleados.id')
            ->where('liquidaciones.id', $id)
            ->select('liquidaciones.*', 'empleados.*')
            ->first();

        return $liquidacion
            ? response()->json($liquidacion)
            : response()->json(['message' => 'No encontrada'], 404);
    }

    public function liquidacionPdf(int $id)
    {
        $liquidacion = DB::table('liquidaciones')
            ->join('empleados', 'liquidaciones.empleado_id', '=', 'empleados.id')
            ->leftJoin('afp', 'empleados.afp_id', '=', 'afp.id')
            ->leftJoin('isapres', 'empleados.salud_id', '=', 'isapres.id')
            ->where('liquidaciones.id', $id)
            ->first();

        $pdf = Pdf::loadView('pdf.liquidacion', compact('liquidacion'));
        return $pdf->download("liquidacion-{$liquidacion->anio}-{$liquidacion->mes}.pdf");
    }

    public function afp(): JsonResponse
    {
        return response()->json(DB::table('afp')->where('activa', true)->get());
    }

    public function isapres(): JsonResponse
    {
        return response()->json(DB::table('isapres')->where('activa', true)->get());
    }

    public function indicadores(): JsonResponse
    {
        return response()->json([
            'uf' => DB::table('indicadores_economicos')->where('codigo', 'UF')->orderByDesc('fecha')->first(),
            'utm' => DB::table('indicadores_economicos')->where('codigo', 'UTM')->orderByDesc('fecha')->first(),
            'sueldo_minimo' => 500000,
            'tope_imponible_uf' => 81.6,
        ]);
    }
}
