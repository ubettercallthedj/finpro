<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

// ========================================
// RRHH CONTROLLER
// ========================================
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

// ========================================
// CONTABILIDAD CONTROLLER
// ========================================
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

// ========================================
// REUNIONES CONTROLLER
// ========================================
class ReunionesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reuniones = DB::table('reuniones')
            ->join('edificios', 'reuniones.edificio_id', '=', 'edificios.id')
            ->where('reuniones.tenant_id', Auth::user()->tenant_id)
            ->when($request->edificio_id, fn($q) => $q->where('reuniones.edificio_id', $request->edificio_id))
            ->when($request->estado, fn($q) => $q->where('reuniones.estado', $request->estado))
            ->select('reuniones.*', 'edificios.nombre as edificio')
            ->orderByDesc('reuniones.fecha_inicio')
            ->paginate(20);

        return response()->json($reuniones);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'titulo' => 'required|string|max:200',
            'tipo' => 'required|in:asamblea_ordinaria,asamblea_extraordinaria,comite_administracion,informativa,emergencia,otro',
            'fecha_inicio' => 'required|date|after:now',
            'modalidad' => 'required|in:presencial,telematica,mixta',
        ]);

        $uuid = Str::uuid();
        $salaUrl = $request->modalidad !== 'presencial'
            ? "https://meet.jit.si/datapolis-{$uuid}"
            : null;

        $id = DB::table('reuniones')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'uuid' => $uuid,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'tipo' => $request->tipo,
            'modalidad' => $request->modalidad,
            'fecha_inicio' => $request->fecha_inicio,
            'duracion_minutos' => $request->duracion_minutos ?? 120,
            'lugar' => $request->lugar,
            'orden_del_dia' => $request->orden_del_dia,
            'quorum_requerido' => $request->quorum_requerido ?? 50,
            'sala_url' => $salaUrl,
            'estado' => 'borrador',
            'creada_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Reunión creada', 'id' => $id, 'uuid' => $uuid], 201);
    }

    public function show(int $id): JsonResponse
    {
        $reunion = DB::table('reuniones')
            ->join('edificios', 'reuniones.edificio_id', '=', 'edificios.id')
            ->where('reuniones.id', $id)
            ->select('reuniones.*', 'edificios.nombre as edificio')
            ->first();

        if (!$reunion) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        $reunion->convocados = DB::table('reunion_convocados')
            ->where('reunion_id', $id)
            ->get();

        $reunion->votaciones = DB::table('votaciones')
            ->where('reunion_id', $id)
            ->orderBy('orden')
            ->get();

        return response()->json($reunion);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        DB::table('reuniones')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(array_merge(
                $request->only(['titulo', 'descripcion', 'fecha_inicio', 'orden_del_dia', 'lugar']),
                ['updated_at' => now()]
            ));

        return response()->json(['message' => 'Actualizada']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('reuniones')->where('id', $id)->update([
            'deleted_at' => now(),
            'estado' => 'cancelada',
        ]);

        return response()->json(['message' => 'Cancelada']);
    }

    public function convocar(int $id): JsonResponse
    {
        $reunion = DB::table('reuniones')->where('id', $id)->first();

        // Agregar todos los propietarios como convocados
        $unidades = DB::table('unidades')
            ->join('personas', 'unidades.propietario_id', '=', 'personas.id')
            ->where('unidades.edificio_id', $reunion->edificio_id)
            ->where('unidades.activa', true)
            ->select('unidades.id as unidad_id', 'personas.id as persona_id',
                     'personas.nombre_completo', 'personas.email', 'unidades.prorrateo')
            ->get();

        foreach ($unidades as $unidad) {
            DB::table('reunion_convocados')->updateOrInsert(
                ['reunion_id' => $id, 'unidad_id' => $unidad->unidad_id],
                [
                    'persona_id' => $unidad->persona_id,
                    'nombre' => $unidad->nombre_completo,
                    'email' => $unidad->email,
                    'prorrateo' => $unidad->prorrateo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('reuniones')->where('id', $id)->update([
            'estado' => 'convocada',
            'convocada_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Reunión convocada']);
    }

    public function iniciar(int $id): JsonResponse
    {
        DB::table('reuniones')->where('id', $id)->update([
            'estado' => 'en_curso',
            'iniciada_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Reunión iniciada']);
    }

    public function finalizar(int $id): JsonResponse
    {
        // Calcular quórum alcanzado
        $presentes = DB::table('reunion_convocados')
            ->where('reunion_id', $id)
            ->where('presente', true)
            ->sum('prorrateo');

        DB::table('reuniones')->where('id', $id)->update([
            'estado' => 'finalizada',
            'finalizada_at' => now(),
            'quorum_alcanzado' => $presentes,
            'quorum_verificado' => true,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Reunión finalizada', 'quorum_alcanzado' => $presentes]);
    }

    public function convocados(int $reunionId): JsonResponse
    {
        $convocados = DB::table('reunion_convocados')
            ->leftJoin('unidades', 'reunion_convocados.unidad_id', '=', 'unidades.id')
            ->where('reunion_convocados.reunion_id', $reunionId)
            ->select('reunion_convocados.*', 'unidades.numero')
            ->orderBy('unidades.numero')
            ->get();

        return response()->json($convocados);
    }

    public function agregarConvocados(Request $request, int $reunionId): JsonResponse
    {
        foreach ($request->convocados as $convocado) {
            DB::table('reunion_convocados')->insert([
                'reunion_id' => $reunionId,
                'unidad_id' => $convocado['unidad_id'],
                'persona_id' => $convocado['persona_id'] ?? null,
                'nombre' => $convocado['nombre'],
                'email' => $convocado['email'] ?? null,
                'prorrateo' => $convocado['prorrateo'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Convocados agregados']);
    }

    public function votaciones(int $reunionId): JsonResponse
    {
        $votaciones = DB::table('votaciones')
            ->where('reunion_id', $reunionId)
            ->orderBy('orden')
            ->get();

        return response()->json($votaciones);
    }

    public function crearVotacion(Request $request, int $reunionId): JsonResponse
    {
        $request->validate([
            'titulo' => 'required|string|max:200',
            'tipo' => 'required|in:si_no,si_no_abstencion,opcion_multiple',
        ]);

        $orden = DB::table('votaciones')->where('reunion_id', $reunionId)->max('orden') + 1;

        $id = DB::table('votaciones')->insertGetId([
            'reunion_id' => $reunionId,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'texto_mocion' => $request->texto_mocion,
            'tipo' => $request->tipo,
            'opciones' => $request->opciones ? json_encode($request->opciones) : null,
            'quorum_tipo' => $request->quorum_tipo ?? 'mayoria_simple',
            'ponderacion' => $request->ponderacion ?? 'por_prorrateo',
            'voto_secreto' => $request->voto_secreto ?? false,
            'estado' => 'pendiente',
            'orden' => $orden,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Votación creada', 'id' => $id], 201);
    }

    public function iniciarVotacion(int $reunionId, int $votacionId): JsonResponse
    {
        DB::table('votaciones')->where('id', $votacionId)->update([
            'estado' => 'abierta',
            'abierta_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Votación iniciada']);
    }

    public function votar(Request $request, int $reunionId, int $votacionId): JsonResponse
    {
        $request->validate([
            'convocado_id' => 'required|exists:reunion_convocados,id',
            'voto' => 'required|string',
        ]);

        $convocado = DB::table('reunion_convocados')->where('id', $request->convocado_id)->first();

        DB::table('votos')->insert([
            'votacion_id' => $votacionId,
            'convocado_id' => $request->convocado_id,
            'voto' => $request->voto,
            'peso' => $convocado->prorrateo,
            'emitido_at' => now(),
            'ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Voto registrado']);
    }

    public function cerrarVotacion(int $reunionId, int $votacionId): JsonResponse
    {
        // Calcular resultados
        $votos = DB::table('votos')
            ->where('votacion_id', $votacionId)
            ->select('voto', DB::raw('SUM(peso) as total'))
            ->groupBy('voto')
            ->get();

        $resultados = $votos->pluck('total', 'voto')->toArray();
        $totalVotos = array_sum($resultados);
        $aprobada = ($resultados['si'] ?? 0) > ($totalVotos / 2);

        DB::table('votaciones')->where('id', $votacionId)->update([
            'estado' => 'cerrada',
            'cerrada_at' => now(),
            'resultados' => json_encode($resultados),
            'aprobada' => $aprobada,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Votación cerrada',
            'resultados' => $resultados,
            'aprobada' => $aprobada,
        ]);
    }

    public function acta(int $reunionId): JsonResponse
    {
        $acta = DB::table('actas')->where('reunion_id', $reunionId)->first();
        return response()->json($acta);
    }

    public function generarActa(int $reunionId): JsonResponse
    {
        $reunion = DB::table('reuniones')
            ->join('edificios', 'reuniones.edificio_id', '=', 'edificios.id')
            ->where('reuniones.id', $reunionId)
            ->first();

        $asistentes = DB::table('reunion_convocados')
            ->where('reunion_id', $reunionId)
            ->where('presente', true)
            ->get();

        $votaciones = DB::table('votaciones')
            ->where('reunion_id', $reunionId)
            ->get();

        $numeroActa = sprintf('ACTA-%04d-%04d', now()->year, $reunionId);

        $id = DB::table('actas')->insertGetId([
            'reunion_id' => $reunionId,
            'numero_acta' => $numeroActa,
            'fecha' => now(),
            'contenido' => "Acta de {$reunion->titulo}",
            'asistentes_total' => $asistentes->count(),
            'asistentes_prorrateo' => $asistentes->sum('prorrateo'),
            'acuerdos' => $votaciones->where('aprobada', true)->pluck('titulo')->toJson(),
            'estado' => 'borrador',
            'redactada_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Acta generada', 'id' => $id], 201);
    }

    public function accederSala(string $uuid): JsonResponse
    {
        $reunion = DB::table('reuniones')
            ->join('edificios', 'reuniones.edificio_id', '=', 'edificios.id')
            ->where('reuniones.uuid', $uuid)
            ->select('reuniones.*', 'edificios.nombre as edificio')
            ->first();

        if (!$reunion) {
            return response()->json(['message' => 'Reunión no encontrada'], 404);
        }

        return response()->json([
            'reunion' => $reunion,
            'sala_url' => $reunion->sala_url,
        ]);
    }
}

// ========================================
// ASISTENTE LEGAL CONTROLLER
// ========================================
class AsistenteLegalController extends Controller
{
    public function index(): JsonResponse
    {
        $consultas = DB::table('consultas_legal')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($consultas);
    }

    public function consultar(Request $request): JsonResponse
    {
        $request->validate([
            'consulta' => 'required|string|max:2000',
        ]);

        // Buscar respuesta en base de conocimiento
        $respuesta = $this->buscarRespuesta($request->consulta);

        $id = DB::table('consultas_legal')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'user_id' => Auth::id(),
            'categoria_id' => $request->categoria_id,
            'consulta' => $request->consulta,
            'respuesta' => $respuesta['texto'],
            'referencias_legales' => json_encode($respuesta['referencias']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'id' => $id,
            'respuesta' => $respuesta['texto'],
            'referencias' => $respuesta['referencias'],
        ]);
    }

    private function buscarRespuesta(string $consulta): array
    {
        // Base de conocimiento simplificada
        $keywords = strtolower($consulta);

        if (str_contains($keywords, 'morosidad') || str_contains($keywords, 'moroso')) {
            return [
                'texto' => 'Según el Art. 14 de la Ley 21.442, el copropietario moroso no puede votar en asambleas. El administrador puede aplicar intereses de hasta 1.5% mensual según el reglamento.',
                'referencias' => ['Ley 21.442 Art. 14', 'Ley 21.442 Art. 5'],
            ];
        }

        if (str_contains($keywords, 'asamblea') || str_contains($keywords, 'quorum')) {
            return [
                'texto' => 'Las asambleas ordinarias requieren quórum de 50% en primera citación. En segunda citación (30 min después) se puede sesionar con los presentes. Las extraordinarias pueden requerir quórums especiales según la materia (Art. 17 Ley 21.442).',
                'referencias' => ['Ley 21.442 Art. 17', 'Ley 21.442 Art. 18'],
            ];
        }

        if (str_contains($keywords, 'arriendo') || str_contains($keywords, 'antena')) {
            return [
                'texto' => 'El arriendo de espacios comunes (como azoteas para antenas) requiere acuerdo de asamblea extraordinaria con quórum de 75%. Los ingresos deben distribuirse proporcionalmente según prorrateo (Ley 21.713).',
                'referencias' => ['Ley 21.442 Art. 17', 'Ley 21.713', 'Art. 17 N°3 LIR'],
            ];
        }

        if (str_contains($keywords, 'distribución') || str_contains($keywords, 'certificado')) {
            return [
                'texto' => 'Según Ley 21.713, los ingresos por arriendo de bienes comunes deben distribuirse y certificarse anualmente. Los montos proporcionales al derecho de cada copropietario están exentos de impuesto (Art. 17 N°3 LIR).',
                'referencias' => ['Ley 21.713', 'Art. 17 N°3 LIR', 'Circular SII 42/2024'],
            ];
        }

        if (str_contains($keywords, 'fondo de reserva')) {
            return [
                'texto' => 'El fondo de reserva debe ser al menos 5% del presupuesto anual (Art. 8 Ley 21.442). Solo puede usarse para gastos extraordinarios o mantenciones mayores, previo acuerdo de asamblea.',
                'referencias' => ['Ley 21.442 Art. 8'],
            ];
        }

        return [
            'texto' => 'Su consulta ha sido registrada. Le recomendamos revisar la Ley 21.442 de Copropiedad Inmobiliaria y su reglamento (DS 7/2025) para información detallada.',
            'referencias' => ['Ley 21.442', 'DS 7/2025 MINVU'],
        ];
    }

    public function categorias(): JsonResponse
    {
        $categorias = DB::table('categorias_legal')
            ->where('activa', true)
            ->orderBy('orden')
            ->get();

        return response()->json($categorias);
    }

    public function faq(): JsonResponse
    {
        $faq = [
            ['pregunta' => '¿Cuál es el quórum para aprobar gastos extraordinarios?', 'categoria' => 'asambleas'],
            ['pregunta' => '¿Cómo se calcula el interés por mora?', 'categoria' => 'gastos-comunes'],
            ['pregunta' => '¿Qué porcentaje requiere aprobar arriendo de espacios comunes?', 'categoria' => 'arriendos'],
            ['pregunta' => '¿Cómo debo distribuir los ingresos por arriendo de antenas?', 'categoria' => 'tributario'],
            ['pregunta' => '¿Cuál es el mínimo del fondo de reserva?', 'categoria' => 'gastos-comunes'],
        ];

        return response()->json($faq);
    }

    public function oficios(Request $request): JsonResponse
    {
        $oficios = DB::table('oficios')
            ->join('instituciones', 'oficios.institucion_id', '=', 'instituciones.id')
            ->where('oficios.tenant_id', Auth::user()->tenant_id)
            ->when($request->estado, fn($q) => $q->where('oficios.estado', $request->estado))
            ->select('oficios.*', 'instituciones.nombre as institucion')
            ->orderByDesc('oficios.fecha')
            ->paginate(20);

        return response()->json($oficios);
    }

    public function crearOficio(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'institucion_id' => 'required|exists:instituciones,id',
            'tipo' => 'required|in:consulta,reclamo,denuncia,solicitud,fiscalizacion,otro',
            'asunto' => 'required|string|max:300',
            'contenido' => 'required|string',
        ]);

        $numero = sprintf('OF-%04d-%06d', now()->year, DB::table('oficios')->count() + 1);

        $id = DB::table('oficios')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'plantilla_id' => $request->plantilla_id,
            'institucion_id' => $request->institucion_id,
            'numero_oficio' => $numero,
            'fecha' => now(),
            'tipo' => $request->tipo,
            'asunto' => $request->asunto,
            'contenido' => $request->contenido,
            'fundamentos_legales' => $request->fundamentos_legales,
            'peticion_concreta' => $request->peticion_concreta,
            'estado' => 'borrador',
            'creado_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Oficio creado', 'id' => $id, 'numero' => $numero], 201);
    }

    public function showOficio(int $id): JsonResponse
    {
        $oficio = DB::table('oficios')
            ->join('instituciones', 'oficios.institucion_id', '=', 'instituciones.id')
            ->join('edificios', 'oficios.edificio_id', '=', 'edificios.id')
            ->where('oficios.id', $id)
            ->select('oficios.*', 'instituciones.nombre as institucion', 'edificios.nombre as edificio')
            ->first();

        return $oficio
            ? response()->json($oficio)
            : response()->json(['message' => 'No encontrado'], 404);
    }

    public function oficioPdf(int $id)
    {
        $oficio = DB::table('oficios')
            ->join('instituciones', 'oficios.institucion_id', '=', 'instituciones.id')
            ->join('edificios', 'oficios.edificio_id', '=', 'edificios.id')
            ->where('oficios.id', $id)
            ->first();

        $pdf = Pdf::loadView('pdf.oficio', compact('oficio'));
        return $pdf->download("oficio-{$oficio->numero_oficio}.pdf");
    }

    public function plantillas(): JsonResponse
    {
        $plantillas = DB::table('plantillas_oficio')
            ->where('activa', true)
            ->orderBy('tipo')
            ->get();

        return response()->json($plantillas);
    }

    public function instituciones(): JsonResponse
    {
        $instituciones = DB::table('instituciones')
            ->where('activa', true)
            ->orderBy('nombre')
            ->get();

        return response()->json($instituciones);
    }

    public function certificados(Request $request): JsonResponse
    {
        $certificados = DB::table('certificados_cumplimiento')
            ->join('edificios', 'certificados_cumplimiento.edificio_id', '=', 'edificios.id')
            ->where('certificados_cumplimiento.tenant_id', Auth::user()->tenant_id)
            ->select('certificados_cumplimiento.*', 'edificios.nombre as edificio')
            ->orderByDesc('fecha_emision')
            ->paginate(20);

        return response()->json($certificados);
    }

    public function generarCertificado(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'tipo' => 'required|in:cumplimiento_general,tributario,ley_21442,transparencia,deuda',
        ]);

        $edificio = DB::table('edificios')->where('id', $request->edificio_id)->first();
        $numero = sprintf('CERT-%04d-%06d', now()->year, DB::table('certificados_cumplimiento')->count() + 1);
        $codigo = strtoupper(substr(md5($numero . now()), 0, 12));

        $contenido = $this->generarContenidoCertificado($request->tipo, $edificio);

        $id = DB::table('certificados_cumplimiento')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'numero_certificado' => $numero,
            'codigo_verificacion' => $codigo,
            'fecha_emision' => now(),
            'fecha_validez' => now()->addMonths(3),
            'tipo' => $request->tipo,
            'titulo' => $this->getTituloCertificado($request->tipo),
            'contenido' => $contenido,
            'emitido_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Certificado generado', 'id' => $id, 'codigo' => $codigo], 201);
    }

    private function getTituloCertificado(string $tipo): string
    {
        return match($tipo) {
            'cumplimiento_general' => 'Certificado de Cumplimiento General',
            'tributario' => 'Certificado de Cumplimiento Tributario',
            'ley_21442' => 'Certificado de Cumplimiento Ley 21.442',
            'transparencia' => 'Certificado de Transparencia',
            'deuda' => 'Certificado de Deuda',
            default => 'Certificado',
        };
    }

    private function generarContenidoCertificado(string $tipo, object $edificio): string
    {
        return "Se certifica que la comunidad {$edificio->nombre}, RUT {$edificio->rut}, cumple con las obligaciones correspondientes al tipo: {$tipo}.";
    }

    public function verificarCertificado(string $codigo): JsonResponse
    {
        $certificado = DB::table('certificados_cumplimiento')
            ->join('edificios', 'certificados_cumplimiento.edificio_id', '=', 'edificios.id')
            ->where('certificados_cumplimiento.codigo_verificacion', $codigo)
            ->select(
                'certificados_cumplimiento.numero_certificado',
                'certificados_cumplimiento.tipo',
                'certificados_cumplimiento.fecha_emision',
                'certificados_cumplimiento.fecha_validez',
                'edificios.nombre as edificio'
            )
            ->first();

        if (!$certificado) {
            return response()->json(['valido' => false, 'message' => 'Certificado no encontrado']);
        }

        $vigente = $certificado->fecha_validez >= now()->toDateString();

        return response()->json([
            'valido' => true,
            'vigente' => $vigente,
            'certificado' => $certificado,
        ]);
    }
}
