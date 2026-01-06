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

// ========================================
// AUTH CONTROLLER
// ========================================
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $requesUnidadControllert->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Bienvenido ' . $user->name,
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'rut' => 'nullable|string|max:12',
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rut' => $request->rut,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Usuario registrado', 'id' => $userId], 201);
    }

    public function user(Request $request): JsonResponse
    {
        $user = Auth::user();
        return response()->json($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'telefono' => 'nullable|string|max:20',
        ]);

        DB::table('users')->where('id', Auth::id())->update([
            'name' => $request->name ?? Auth::user()->name,
            'telefono' => $request->telefono,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Perfil actualizado']);
    }
}

// ========================================
// DASHBOARD CONTROLLER
// ========================================
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

// ========================================
// EDIFICIO CONTROLLER
// ========================================
class EdificioController extends Controller
{
    public function index(): JsonResponse
    {
        $edificios = DB::table('edificios')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get();

        return response()->json($edificios);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'direccion' => 'required|string|max:300',
            'comuna' => 'required|string|max:100',
            'rut' => 'required|string|max:12|unique:edificios,rut',
            'tipo' => 'required|in:condominio,comunidad,edificio',
            'total_unidades' => 'required|integer|min:1',
        ]);

        $id = DB::table('edificios')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'nombre' => $request->nombre,
            'direccion' => $request->direccion,
            'comuna' => $request->comuna,
            'region' => $request->region ?? 'Metropolitana',
            'rut' => $request->rut,
            'tipo' => $request->tipo,
            'total_unidades' => $request->total_unidades,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Edificio creado', 'id' => $id], 201);
    }

    public function show(int $id): JsonResponse
    {
        $edificio = DB::table('edificios')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->first();

        if (!$edificio) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        return response()->json($edificio);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        DB::table('edificios')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(array_merge($request->only([
                'nombre', 'direccion', 'comuna', 'administrador_nombre',
                'administrador_email', 'dia_vencimiento_gc', 'interes_mora',
            ]), ['updated_at' => now()]));

        return response()->json(['message' => 'Actualizado']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('edificios')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(['deleted_at' => now()]);

        return response()->json(['message' => 'Eliminado']);
    }

    public function unidades(int $edificioId): JsonResponse
    {
        $unidades = DB::table('unidades')
            ->leftJoin('personas as p', 'unidades.propietario_id', '=', 'p.id')
            ->where('unidades.edificio_id', $edificioId)
            ->select('unidades.*', 'p.nombre_completo as propietario')
            ->orderBy('unidades.numero')
            ->get();

        return response()->json($unidades);
    }

    public function estadisticas(int $edificioId): JsonResponse
    {
        $stats = [
            'unidades' => DB::table('unidades')->where('edificio_id', $edificioId)->count(),
            'morosidad' => DB::table('boletas_gc')
                ->where('edificio_id', $edificioId)
                ->whereIn('estado', ['pendiente', 'vencida'])
                ->sum(DB::raw('total_a_pagar - COALESCE(total_abonos, 0)')),
            'contratos' => DB::table('contratos_arriendo')
                ->where('edificio_id', $edificioId)
                ->where('estado', 'activo')
                ->count(),
        ];

        return response()->json($stats);
    }
}

// ========================================
// UNIDAD CONTROLLER
// ========================================
class UnidadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('unidades')
            ->leftJoin('personas as p', 'unidades.propietario_id', '=', 'p.id')
            ->leftJoin('edificios', 'unidades.edificio_id', '=', 'edificios.id')
            ->where('unidades.tenant_id', Auth::user()->tenant_id)
            ->select('unidades.*', 'p.nombre_completo as propietario', 'edificios.nombre as edificio');

        if ($request->edificio_id) {
            $query->where('unidades.edificio_id', $request->edificio_id);
        }

        return response()->json($query->orderBy('edificios.nombre')->orderBy('unidades.numero')->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'numero' => 'required|string|max:20',
            'tipo' => 'required|in:departamento,casa,local,oficina,bodega,estacionamiento',
            'prorrateo' => 'required|numeric|min:0|max:100',
        ]);

        $id = DB::table('unidades')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'edificio_id' => $request->edificio_id,
            'numero' => $request->numero,
            'tipo' => $request->tipo,
            'piso' => $request->piso,
            'superficie_util' => $request->superficie_util,
            'prorrateo' => $request->prorrateo,
            'propietario_id' => $request->propietario_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Unidad creada', 'id' => $id], 201);
    }

    public function show(int $id): JsonResponse
    {
        $unidad = DB::table('unidades')
            ->leftJoin('personas as prop', 'unidades.propietario_id', '=', 'prop.id')
            ->leftJoin('personas as res', 'unidades.residente_id', '=', 'res.id')
            ->leftJoin('edificios', 'unidades.edificio_id', '=', 'edificios.id')
            ->where('unidades.id', $id)
            ->select(
                'unidades.*',
                'prop.nombre_completo as propietario_nombre',
                'prop.email as propietario_email',
                'res.nombre_completo as residente_nombre',
                'edificios.nombre as edificio_nombre'
            )
            ->first();

        if (!$unidad) {
            return response()->json(['message' => 'No encontrada'], 404);
        }

        $unidad->saldo = DB::table('boletas_gc')
            ->where('unidad_id', $id)
            ->whereIn('estado', ['pendiente', 'vencida'])
            ->sum(DB::raw('total_a_pagar - COALESCE(total_abonos, 0)'));

        return response()->json($unidad);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        DB::table('unidades')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(array_merge(
                $request->only(['numero', 'tipo', 'piso', 'superficie_util', 'prorrateo', 'propietario_id', 'residente_id']),
                ['updated_at' => now()]
            ));

        return response()->json(['message' => 'Actualizada']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('unidades')->where('id', $id)->update(['deleted_at' => now()]);
        return response()->json(['message' => 'Eliminada']);
    }

    public function boletas(int $unidadId): JsonResponse
    {
        $boletas = DB::table('boletas_gc')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->where('boletas_gc.unidad_id', $unidadId)
            ->select('boletas_gc.*', 'periodos_gc.mes', 'periodos_gc.anio')
            ->orderByDesc('periodos_gc.anio')
            ->orderByDesc('periodos_gc.mes')
            ->paginate(24);

        return response()->json($boletas);
    }

    public function estadoCuenta(int $unidadId): JsonResponse
    {
        $boletas = DB::table('boletas_gc')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->where('boletas_gc.unidad_id', $unidadId)
            ->whereIn('boletas_gc.estado', ['pendiente', 'vencida'])
            ->select('boletas_gc.*', 'periodos_gc.mes', 'periodos_gc.anio')
            ->orderBy('periodos_gc.anio')
            ->orderBy('periodos_gc.mes')
            ->get();

        return response()->json([
            'saldo_total' => $boletas->sum(fn($b) => $b->total_a_pagar - ($b->total_abonos ?? 0)),
            'boletas' => $boletas,
        ]);
    }
}

// ========================================
// PERSONA CONTROLLER
// ========================================
class PersonaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('personas')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNull('deleted_at');

        if ($request->buscar) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre_completo', 'like', "%{$request->buscar}%")
                  ->orWhere('rut', 'like', "%{$request->buscar}%")
                  ->orWhere('email', 'like', "%{$request->buscar}%");
            });
        }

        return response()->json($query->orderBy('nombre_completo')->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'rut' => 'required|string|max:12',
            'tipo_persona' => 'required|in:natural,juridica',
        ]);

        $id = DB::table('personas')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'rut' => $request->rut,
            'tipo_persona' => $request->tipo_persona,
            'nombre' => $request->nombre,
            'apellido_paterno' => $request->apellido_paterno,
            'apellido_materno' => $request->apellido_materno,
            'razon_social' => $request->razon_social,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'direccion' => $request->direccion,
            'comuna' => $request->comuna,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Persona creada', 'id' => $id], 201);
    }

    public function show(int $id): JsonResponse
    {
        $persona = DB::table('personas')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->first();

        return $persona
            ? response()->json($persona)
            : response()->json(['message' => 'No encontrada'], 404);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        DB::table('personas')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update(array_merge(
                $request->only(['nombre', 'apellido_paterno', 'apellido_materno', 'email', 'telefono', 'direccion']),
                ['updated_at' => now()]
            ));

        return response()->json(['message' => 'Actualizada']);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('personas')->where('id', $id)->update(['deleted_at' => now()]);
        return response()->json(['message' => 'Eliminada']);
    }
}

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


// ========================================
// PROTECCIÓN DE DATOS
// ========================================
/**
 * CONTROLADOR DE PROTECCIÓN DE DATOS PERSONALES
 * Cumplimiento Ley 19.628 / Ley 21.719 (2024)
 */
class ProteccionDatosController extends Controller
{
    // ========================================
    // DERECHOS ARCO+ DEL TITULAR
    // ========================================

    /**
     * Ejercer derecho de ACCESO (Art. 4 Ley 21.719)
     * El titular puede conocer qué datos personales se tratan
     */
    public function ejercerDerechoAcceso(Request $request): JsonResponse
    {
        $request->validate([
            'rut' => 'required|string|max:12',
            'email' => 'required|email',
            'motivo' => 'nullable|string|max:500',
        ]);

        // Crear solicitud
        $numero = 'ARCO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $id = DB::table('solicitudes_derechos_datos')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id ?? 1,
            'numero_solicitud' => $numero,
            'nombre_solicitante' => $request->nombre ?? 'Pendiente verificación',
            'rut_solicitante' => $request->rut,
            'email_solicitante' => $request->email,
            'telefono_solicitante' => $request->telefono,
            'tipo_derecho' => 'acceso',
            'descripcion_solicitud' => $request->motivo ?? 'Solicitud de acceso a datos personales',
            'estado' => 'recibida',
            'fecha_recepcion' => now(),
            'fecha_limite_respuesta' => now()->addWeekdays(10), // 10 días hábiles
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log de la solicitud
        $this->logAccesoPersonal('solicitudes_derechos_datos', $id, null, 'creacion', 'Solicitud derecho acceso');

        return response()->json([
            'message' => 'Solicitud recibida correctamente',
            'numero_solicitud' => $numero,
            'plazo_respuesta' => '10 días hábiles',
            'fecha_limite' => now()->addWeekdays(10)->format('d/m/Y'),
        ], 201);
    }

    /**
     * Ejercer derecho de RECTIFICACIÓN (Art. 5 Ley 21.719)
     */
    public function ejercerDerechoRectificacion(Request $request): JsonResponse
    {
        $request->validate([
            'rut' => 'required|string|max:12',
            'email' => 'required|email',
            'datos_incorrectos' => 'required|array',
            'datos_correctos' => 'required|array',
            'evidencia' => 'nullable|file|max:10240',
        ]);

        $numero = 'ARCO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $id = DB::table('solicitudes_derechos_datos')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id ?? 1,
            'numero_solicitud' => $numero,
            'nombre_solicitante' => $request->nombre ?? 'Pendiente verificación',
            'rut_solicitante' => $request->rut,
            'email_solicitante' => $request->email,
            'tipo_derecho' => 'rectificacion',
            'descripcion_solicitud' => 'Solicitud de rectificación de datos',
            'datos_afectados' => json_encode([
                'incorrectos' => $request->datos_incorrectos,
                'correctos' => $request->datos_correctos,
            ]),
            'motivo' => $request->motivo,
            'estado' => 'recibida',
            'fecha_recepcion' => now(),
            'fecha_limite_respuesta' => now()->addWeekdays(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Solicitud de rectificación recibida',
            'numero_solicitud' => $numero,
        ], 201);
    }

    /**
     * Ejercer derecho de CANCELACIÓN/SUPRESIÓN (Art. 6 Ley 21.719)
     */
    public function ejercerDerechoCancelacion(Request $request): JsonResponse
    {
        $request->validate([
            'rut' => 'required|string|max:12',
            'email' => 'required|email',
            'motivo' => 'required|string|max:1000',
            'datos_a_eliminar' => 'nullable|array',
        ]);

        $numero = 'ARCO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $id = DB::table('solicitudes_derechos_datos')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id ?? 1,
            'numero_solicitud' => $numero,
            'nombre_solicitante' => $request->nombre ?? 'Pendiente verificación',
            'rut_solicitante' => $request->rut,
            'email_solicitante' => $request->email,
            'tipo_derecho' => 'cancelacion',
            'descripcion_solicitud' => 'Solicitud de eliminación de datos personales',
            'datos_afectados' => $request->datos_a_eliminar ? json_encode($request->datos_a_eliminar) : null,
            'motivo' => $request->motivo,
            'estado' => 'recibida',
            'fecha_recepcion' => now(),
            'fecha_limite_respuesta' => now()->addWeekdays(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Solicitud de cancelación recibida',
            'numero_solicitud' => $numero,
            'nota' => 'Se evaluará si aplican excepciones legales (obligaciones tributarias, laborales, etc.)',
        ], 201);
    }

    /**
     * Ejercer derecho de OPOSICIÓN (Art. 7 Ley 21.719)
     */
    public function ejercerDerechoOposicion(Request $request): JsonResponse
    {
        $request->validate([
            'rut' => 'required|string|max:12',
            'email' => 'required|email',
            'tratamiento_opuesto' => 'required|string',
            'motivo' => 'required|string|max:1000',
        ]);

        $numero = 'ARCO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        DB::table('solicitudes_derechos_datos')->insert([
            'tenant_id' => Auth::user()->tenant_id ?? 1,
            'numero_solicitud' => $numero,
            'nombre_solicitante' => $request->nombre ?? 'Pendiente verificación',
            'rut_solicitante' => $request->rut,
            'email_solicitante' => $request->email,
            'tipo_derecho' => 'oposicion',
            'descripcion_solicitud' => "Oposición a tratamiento: {$request->tratamiento_opuesto}",
            'motivo' => $request->motivo,
            'estado' => 'recibida',
            'fecha_recepcion' => now(),
            'fecha_limite_respuesta' => now()->addWeekdays(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Solicitud de oposición recibida',
            'numero_solicitud' => $numero,
        ], 201);
    }

    /**
     * Ejercer derecho de PORTABILIDAD (Art. 8 Ley 21.719)
     */
    public function ejercerDerechoPortabilidad(Request $request): JsonResponse
    {
        $request->validate([
            'rut' => 'required|string|max:12',
            'email' => 'required|email',
            'formato_preferido' => 'nullable|in:json,csv,xml',
        ]);

        $numero = 'ARCO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        DB::table('solicitudes_derechos_datos')->insert([
            'tenant_id' => Auth::user()->tenant_id ?? 1,
            'numero_solicitud' => $numero,
            'nombre_solicitante' => $request->nombre ?? 'Pendiente verificación',
            'rut_solicitante' => $request->rut,
            'email_solicitante' => $request->email,
            'tipo_derecho' => 'portabilidad',
            'descripcion_solicitud' => 'Solicitud de portabilidad de datos en formato ' . ($request->formato_preferido ?? 'JSON'),
            'estado' => 'recibida',
            'fecha_recepcion' => now(),
            'fecha_limite_respuesta' => now()->addWeekdays(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Solicitud de portabilidad recibida',
            'numero_solicitud' => $numero,
            'formatos_disponibles' => ['json', 'csv', 'xml'],
        ], 201);
    }

    /**
     * Consultar estado de solicitud ARCO
     */
    public function consultarEstadoSolicitud(string $numero): JsonResponse
    {
        $solicitud = DB::table('solicitudes_derechos_datos')
            ->where('numero_solicitud', $numero)
            ->first(['numero_solicitud', 'tipo_derecho', 'estado', 'fecha_recepcion', 
                     'fecha_limite_respuesta', 'fecha_respuesta', 'respuesta']);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        return response()->json($solicitud);
    }

    /**
     * Listar solicitudes (admin)
     */
    public function listarSolicitudes(Request $request): JsonResponse
    {
        $solicitudes = DB::table('solicitudes_derechos_datos')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
            ->when($request->tipo, fn($q) => $q->where('tipo_derecho', $request->tipo))
            ->orderByDesc('fecha_recepcion')
            ->paginate(20);

        return response()->json($solicitudes);
    }

    /**
     * Procesar solicitud (admin)
     */
    public function procesarSolicitud(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:aprobada,rechazada,completada',
            'respuesta' => 'required|string',
            'motivo_rechazo' => 'nullable|string',
            'acciones_realizadas' => 'nullable|array',
        ]);

        DB::table('solicitudes_derechos_datos')
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->update([
                'estado' => $request->estado,
                'respuesta' => $request->respuesta,
                'motivo_rechazo' => $request->motivo_rechazo,
                'acciones_realizadas' => $request->acciones_realizadas ? json_encode($request->acciones_realizadas) : null,
                'fecha_respuesta' => now(),
                'atendido_por' => Auth::id(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Solicitud procesada']);
    }

    // ========================================
    // CONSENTIMIENTOS
    // ========================================

    /**
     * Registrar consentimiento
     */
    public function registrarConsentimiento(Request $request): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'tratamiento_id' => 'required|exists:registro_tratamiento_datos,id',
            'tipo' => 'required|in:general,marketing,compartir_terceros,datos_sensibles,perfilamiento,internacional',
            'otorgado' => 'required|boolean',
        ]);

        $tratamiento = DB::table('registro_tratamiento_datos')
            ->where('id', $request->tratamiento_id)
            ->first();

        $politica = DB::table('politicas_privacidad')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('vigente', true)
            ->first();

        DB::table('consentimientos_datos')->updateOrInsert(
            [
                'persona_id' => $request->persona_id,
                'tratamiento_id' => $request->tratamiento_id,
                'tipo' => $request->tipo,
            ],
            [
                'tenant_id' => Auth::user()->tenant_id,
                'otorgado' => $request->otorgado,
                'fecha_otorgamiento' => $request->otorgado ? now() : null,
                'fecha_revocacion' => !$request->otorgado ? now() : null,
                'ip_otorgamiento' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'texto_consentimiento' => $tratamiento->finalidad_tratamiento ?? '',
                'version_politica' => $politica->version ?? '1.0',
                'metodo_obtencion' => 'web',
                'updated_at' => now(),
            ]
        );

        $this->logAccesoPersonal('consentimientos_datos', $request->persona_id, $request->persona_id, 
            'creacion', 'Registro de consentimiento: ' . $request->tipo);

        return response()->json(['message' => $request->otorgado ? 'Consentimiento registrado' : 'Consentimiento revocado']);
    }

    /**
     * Revocar consentimiento
     */
    public function revocarConsentimiento(Request $request): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'tipo' => 'required|string',
        ]);

        DB::table('consentimientos_datos')
            ->where('persona_id', $request->persona_id)
            ->where('tipo', $request->tipo)
            ->update([
                'otorgado' => false,
                'fecha_revocacion' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Consentimiento revocado']);
    }

    /**
     * Obtener consentimientos de una persona
     */
    public function obtenerConsentimientos(int $personaId): JsonResponse
    {
        $consentimientos = DB::table('consentimientos_datos')
            ->leftJoin('registro_tratamiento_datos', 'consentimientos_datos.tratamiento_id', '=', 'registro_tratamiento_datos.id')
            ->where('consentimientos_datos.persona_id', $personaId)
            ->select(
                'consentimientos_datos.*',
                'registro_tratamiento_datos.nombre_tratamiento',
                'registro_tratamiento_datos.finalidad_tratamiento'
            )
            ->get();

        return response()->json($consentimientos);
    }

    // ========================================
    // REGISTRO DE TRATAMIENTOS
    // ========================================

    /**
     * Listar tratamientos de datos
     */
    public function listarTratamientos(): JsonResponse
    {
        $tratamientos = DB::table('registro_tratamiento_datos')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('estado', 'activo')
            ->orderBy('nombre_tratamiento')
            ->get();

        return response()->json($tratamientos);
    }

    /**
     * Crear registro de tratamiento
     */
    public function crearTratamiento(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_tratamiento' => 'required|string|max:200',
            'descripcion' => 'required|string',
            'categoria_datos' => 'required|in:identificacion,contacto,financieros,laborales,salud,biometricos,ubicacion,comportamiento',
            'base_legal' => 'required|in:consentimiento,ejecucion_contrato,obligacion_legal,interes_vital,interes_publico,interes_legitimo',
            'finalidad_tratamiento' => 'required|string',
            'periodo_retencion_meses' => 'required|integer|min:1',
        ]);

        $id = DB::table('registro_tratamiento_datos')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'nombre_tratamiento' => $request->nombre_tratamiento,
            'descripcion' => $request->descripcion,
            'categoria_datos' => $request->categoria_datos,
            'datos_sensibles' => in_array($request->categoria_datos, ['salud', 'biometricos']),
            'base_legal' => $request->base_legal,
            'justificacion_base_legal' => $request->justificacion_base_legal,
            'finalidad_tratamiento' => $request->finalidad_tratamiento,
            'usos_permitidos' => json_encode($request->usos_permitidos ?? []),
            'campos_recolectados' => json_encode($request->campos_recolectados ?? []),
            'justificacion_campos' => $request->justificacion_campos,
            'periodo_retencion_meses' => $request->periodo_retencion_meses,
            'justificacion_retencion' => $request->justificacion_retencion,
            'accion_post_retencion' => $request->accion_post_retencion ?? 'eliminacion',
            'transferencia_terceros' => $request->transferencia_terceros ?? false,
            'destinatarios_transferencia' => $request->destinatarios_transferencia ? json_encode($request->destinatarios_transferencia) : null,
            'transferencia_internacional' => $request->transferencia_internacional ?? false,
            'paises_destino' => $request->paises_destino,
            'medidas_seguridad' => json_encode($request->medidas_seguridad ?? ['encriptacion', 'control_acceso', 'logs']),
            'responsable_id' => Auth::id(),
            'proxima_revision' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Tratamiento registrado', 'id' => $id], 201);
    }

    // ========================================
    // BRECHAS DE SEGURIDAD
    // ========================================

    /**
     * Reportar brecha de seguridad
     */
    public function reportarBrecha(Request $request): JsonResponse
    {
        $request->validate([
            'descripcion' => 'required|string',
            'tipo_brecha' => 'required|in:acceso_no_autorizado,perdida_datos,robo_datos,divulgacion_accidental,ataque_cibernetico,error_humano,falla_sistema',
            'tipos_datos_afectados' => 'required|array',
            'cantidad_registros_afectados' => 'nullable|integer',
        ]);

        $numero = 'BRECHA-' . date('YmdHis');

        $id = DB::table('brechas_seguridad_datos')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'numero_incidente' => $numero,
            'fecha_deteccion' => now(),
            'fecha_ocurrencia' => $request->fecha_ocurrencia,
            'descripcion' => $request->descripcion,
            'tipo_brecha' => $request->tipo_brecha,
            'tipos_datos_afectados' => json_encode($request->tipos_datos_afectados),
            'cantidad_registros_afectados' => $request->cantidad_registros_afectados,
            'cantidad_titulares_afectados' => $request->cantidad_titulares_afectados,
            'datos_sensibles_afectados' => $request->datos_sensibles_afectados ?? false,
            'nivel_riesgo' => $request->nivel_riesgo ?? 'medio',
            'evaluacion_impacto' => $request->evaluacion_impacto ?? 'Pendiente de evaluación',
            'riesgo_derechos_libertades' => $request->riesgo_derechos_libertades ?? false,
            'medidas_contencion' => json_encode($request->medidas_contencion ?? []),
            'medidas_correctivas' => json_encode([]),
            'estado' => 'detectada',
            'detectado_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Alerta: Si es alto riesgo, hay 72 horas para notificar a la Agencia
        if (in_array($request->nivel_riesgo, ['alto', 'critico']) || $request->datos_sensibles_afectados) {
            // Aquí iría la notificación automática
        }

        return response()->json([
            'message' => 'Brecha reportada',
            'numero_incidente' => $numero,
            'alerta' => $request->nivel_riesgo === 'critico' 
                ? 'URGENTE: Debe notificar a la Agencia de Protección de Datos en 72 horas'
                : null,
        ], 201);
    }

    /**
     * Listar brechas
     */
    public function listarBrechas(): JsonResponse
    {
        $brechas = DB::table('brechas_seguridad_datos')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->orderByDesc('fecha_deteccion')
            ->paginate(20);

        return response()->json($brechas);
    }

    // ========================================
    // POLÍTICAS DE PRIVACIDAD
    // ========================================

    /**
     * Obtener política vigente
     */
    public function obtenerPoliticaVigente(): JsonResponse
    {
        $politica = DB::table('politicas_privacidad')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('vigente', true)
            ->first();

        return response()->json($politica);
    }

    /**
     * Crear nueva versión de política
     */
    public function crearPolitica(Request $request): JsonResponse
    {
        $request->validate([
            'version' => 'required|string|max:20',
            'titulo' => 'required|string|max:200',
            'contenido_html' => 'required|string',
            'fecha_vigencia' => 'required|date',
            'requiere_nuevo_consentimiento' => 'required|boolean',
        ]);

        // Desactivar política anterior
        DB::table('politicas_privacidad')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('vigente', true)
            ->update(['vigente' => false, 'fecha_fin_vigencia' => now()]);

        $id = DB::table('politicas_privacidad')->insertGetId([
            'tenant_id' => Auth::user()->tenant_id,
            'version' => $request->version,
            'titulo' => $request->titulo,
            'contenido_html' => $request->contenido_html,
            'contenido_texto' => strip_tags($request->contenido_html),
            'cambios_desde_anterior' => $request->cambios ? json_encode($request->cambios) : null,
            'resumen_cambios' => $request->resumen_cambios,
            'fecha_vigencia' => $request->fecha_vigencia,
            'vigente' => true,
            'requiere_nuevo_consentimiento' => $request->requiere_nuevo_consentimiento,
            'creado_por' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Política creada', 'id' => $id], 201);
    }

    // ========================================
    // ANONIMIZACIÓN
    // ========================================

    /**
     * Anonimizar datos de una persona
     */
    public function anonimizarDatos(Request $request): JsonResponse
    {
        $request->validate([
            'persona_id' => 'required|exists:personas,id',
            'motivo' => 'required|string',
        ]);

        $persona = DB::table('personas')->where('id', $request->persona_id)->first();

        // Guardar datos originales anonimizados para auditoría
        DB::table('datos_anonimizados')->insert([
            'tenant_id' => Auth::user()->tenant_id,
            'tabla_origen' => 'personas',
            'registro_origen_id' => $request->persona_id,
            'datos_anonimizados' => json_encode([
                'rut' => hash('sha256', $persona->rut),
                'nombre' => 'ANONIMIZADO',
                'email' => hash('sha256', $persona->email ?? ''),
            ]),
            'tipo' => 'anonimizacion',
            'algoritmo_usado' => 'sha256',
            'motivo' => $request->motivo,
            'ejecutado_por' => Auth::id(),
            'created_at' => now(),
        ]);

        // Anonimizar en tabla principal
        DB::table('personas')->where('id', $request->persona_id)->update([
            'rut' => 'ANON-' . $request->persona_id,
            'nombre' => 'ELIMINADO',
            'apellido_paterno' => 'POR',
            'apellido_materno' => 'SOLICITUD',
            'nombre_completo' => 'DATOS ELIMINADOS POR SOLICITUD',
            'email' => null,
            'telefono' => null,
            'direccion' => null,
            'updated_at' => now(),
        ]);

        $this->logAccesoPersonal('personas', $request->persona_id, $request->persona_id, 
            'eliminacion', 'Anonimización por: ' . $request->motivo);

        return response()->json(['message' => 'Datos anonimizados correctamente']);
    }

    // ========================================
    // LOGS DE ACCESO
    // ========================================

    /**
     * Registrar acceso a datos personales
     */
    public function logAccesoPersonal(
        string $tabla,
        int $registroId,
        ?int $personaAfectadaId,
        string $operacion,
        ?string $motivo = null
    ): void {
        DB::table('log_acceso_datos_personales')->insert([
            'tenant_id' => Auth::user()->tenant_id ?? 1,
            'user_id' => Auth::id(),
            'tabla_accedida' => $tabla,
            'registro_id' => $registroId,
            'persona_afectada_id' => $personaAfectadaId,
            'campos_accedidos' => json_encode(['*']),
            'operacion' => $operacion,
            'motivo' => $motivo,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent(),
            'endpoint' => request()->path(),
            'exitoso' => true,
            'created_at' => now(),
        ]);
    }

    /**
     * Obtener logs de acceso a datos de una persona
     */
    public function logsAccesoPersona(int $personaId): JsonResponse
    {
        $logs = DB::table('log_acceso_datos_personales')
            ->leftJoin('users', 'log_acceso_datos_personales.user_id', '=', 'users.id')
            ->where('persona_afectada_id', $personaId)
            ->select(
                'log_acceso_datos_personales.*',
                'users.name as usuario'
            )
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }

    // ========================================
    // DASHBOARD DE CUMPLIMIENTO
    // ========================================

    /**
     * Dashboard de cumplimiento de protección de datos
     */
    public function dashboardCumplimiento(): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        return response()->json([
            'solicitudes' => [
                'pendientes' => DB::table('solicitudes_derechos_datos')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('estado', ['recibida', 'en_proceso'])
                    ->count(),
                'vencidas' => DB::table('solicitudes_derechos_datos')
                    ->where('tenant_id', $tenantId)
                    ->where('fecha_limite_respuesta', '<', now())
                    ->whereNotIn('estado', ['completada', 'rechazada'])
                    ->count(),
                'total_mes' => DB::table('solicitudes_derechos_datos')
                    ->where('tenant_id', $tenantId)
                    ->whereMonth('fecha_recepcion', now()->month)
                    ->count(),
            ],
            'consentimientos' => [
                'activos' => DB::table('consentimientos_datos')
                    ->where('tenant_id', $tenantId)
                    ->where('otorgado', true)
                    ->count(),
                'revocados_mes' => DB::table('consentimientos_datos')
                    ->where('tenant_id', $tenantId)
                    ->where('otorgado', false)
                    ->whereMonth('fecha_revocacion', now()->month)
                    ->count(),
            ],
            'brechas' => [
                'abiertas' => DB::table('brechas_seguridad_datos')
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('estado', ['resuelta', 'cerrada'])
                    ->count(),
                'criticas' => DB::table('brechas_seguridad_datos')
                    ->where('tenant_id', $tenantId)
                    ->where('nivel_riesgo', 'critico')
                    ->whereNotIn('estado', ['resuelta', 'cerrada'])
                    ->count(),
            ],
            'tratamientos' => [
                'activos' => DB::table('registro_tratamiento_datos')
                    ->where('tenant_id', $tenantId)
                    ->where('estado', 'activo')
                    ->count(),
                'requieren_revision' => DB::table('registro_tratamiento_datos')
                    ->where('tenant_id', $tenantId)
                    ->where('proxima_revision', '<', now())
                    ->count(),
            ],
            'politica_vigente' => DB::table('politicas_privacidad')
                ->where('tenant_id', $tenantId)
                ->where('vigente', true)
                ->value('version'),
        ]);
    }
}

     // =========================================================================
    //  REPORTES TRIBUTARIOS
    // =========================================================================
/**
 * DATAPOLIS PRO - Controlador de Reportes Tributarios
 * 
 * Gestiona:
 * - Balance General (formato SII/F22)
 * - Estado de Resultados
 * - Declaraciones Juradas (DJ 1887)
 * - Reportes consolidados de distribución
 * - Certificados tributarios
 */
class ReportesTributariosController extends Controller
{
    // =========================================================================
    // BALANCE GENERAL
    // =========================================================================

    /**
     * Listar balances generales
     */
    public function listarBalances(Request $request): JsonResponse
    {
        $balances = DB::table('balances_generales')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->when($request->edificio_id, fn($q, $v) => $q->where('edificio_id', $v))
            ->when($request->anio, fn($q, $v) => $q->where('anio_tributario', $v))
            ->orderByDesc('anio_tributario')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($balances);
    }

    /**
     * Generar Balance General
     */
    public function generarBalanceGeneral(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'anio_tributario' => 'required|integer|min:2020|max:2030',
            'tipo' => 'required|in:anual,mensual,trimestral,semestral',
            'mes' => 'nullable|integer|min:1|max:12',
            'fecha_inicio' => 'required|date',
            'fecha_cierre' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $edificioId = $request->edificio_id;
        $fechaInicio = $request->fecha_inicio;
        $fechaCierre = $request->fecha_cierre;

        // Calcular saldos desde asientos contables
        $saldosCuentas = $this->calcularSaldosCuentas($tenantId, $edificioId, $fechaCierre);

        // Mapear a estructura de balance
        $balance = [
            'tenant_id' => $tenantId,
            'edificio_id' => $edificioId,
            'anio_tributario' => $request->anio_tributario,
            'tipo' => $request->tipo,
            'mes' => $request->mes,
            'fecha_inicio' => $fechaInicio,
            'fecha_cierre' => $fechaCierre,

            // ACTIVOS CIRCULANTES
            'activo_circulante_caja' => $saldosCuentas['1.1.1'] ?? 0,
            'activo_circulante_bancos' => $saldosCuentas['1.1.2'] ?? 0,
            'activo_circulante_cuentas_cobrar' => $saldosCuentas['1.1.3'] ?? 0,
            'activo_circulante_documentos_cobrar' => $saldosCuentas['1.1.4'] ?? 0,
            'activo_circulante_deudores_gc' => $this->calcularDeudoresGC($tenantId, $edificioId, $fechaCierre),
            'activo_circulante_arriendos_cobrar' => $this->calcularArriendosPorCobrar($tenantId, $edificioId, $fechaCierre),
            'activo_circulante_iva_credito' => $saldosCuentas['1.1.6'] ?? 0,
            'activo_circulante_otros' => $saldosCuentas['1.1.9'] ?? 0,

            // ACTIVOS FIJOS
            'activo_fijo_terrenos' => $saldosCuentas['1.2.1'] ?? 0,
            'activo_fijo_construcciones' => $saldosCuentas['1.2.2'] ?? 0,
            'activo_fijo_muebles' => $saldosCuentas['1.2.3'] ?? 0,
            'activo_fijo_equipos' => $saldosCuentas['1.2.4'] ?? 0,
            'activo_fijo_vehiculos' => $saldosCuentas['1.2.5'] ?? 0,
            'activo_fijo_depreciacion_acum' => $saldosCuentas['1.2.9'] ?? 0,

            'otros_activos' => $saldosCuentas['1.3'] ?? 0,

            // PASIVOS CIRCULANTES
            'pasivo_circulante_proveedores' => $saldosCuentas['2.1.1'] ?? 0,
            'pasivo_circulante_remuneraciones' => $saldosCuentas['2.1.2'] ?? 0,
            'pasivo_circulante_cotizaciones' => $saldosCuentas['2.1.3'] ?? 0,
            'pasivo_circulante_impuestos' => $saldosCuentas['2.1.4'] ?? 0,
            'pasivo_circulante_iva_debito' => $saldosCuentas['2.1.5'] ?? 0,
            'pasivo_circulante_arriendos_anticipados' => $saldosCuentas['2.1.6'] ?? 0,
            'pasivo_circulante_otros' => $saldosCuentas['2.1.9'] ?? 0,

            // PASIVOS LARGO PLAZO
            'pasivo_largo_plazo_deudas' => $saldosCuentas['2.2.1'] ?? 0,
            'pasivo_largo_plazo_provisiones' => $saldosCuentas['2.2.2'] ?? 0,

            // PATRIMONIO
            'patrimonio_fondo_comun' => $saldosCuentas['3.1.1'] ?? 0,
            'patrimonio_fondo_reserva' => $this->calcularFondoReserva($tenantId, $edificioId, $fechaCierre),
            'patrimonio_resultados_acumulados' => $saldosCuentas['3.2.1'] ?? 0,
            'patrimonio_resultado_ejercicio' => $saldosCuentas['3.2.2'] ?? 0,

            'estado' => 'generado',
            'generado_por' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Calcular totales
        $balance['total_activo_circulante'] = 
            $balance['activo_circulante_caja'] +
            $balance['activo_circulante_bancos'] +
            $balance['activo_circulante_cuentas_cobrar'] +
            $balance['activo_circulante_documentos_cobrar'] +
            $balance['activo_circulante_deudores_gc'] +
            $balance['activo_circulante_arriendos_cobrar'] +
            $balance['activo_circulante_iva_credito'] +
            $balance['activo_circulante_otros'];

        $balance['total_activo_fijo'] = 
            $balance['activo_fijo_terrenos'] +
            $balance['activo_fijo_construcciones'] +
            $balance['activo_fijo_muebles'] +
            $balance['activo_fijo_equipos'] +
            $balance['activo_fijo_vehiculos'] -
            abs($balance['activo_fijo_depreciacion_acum']);

        $balance['total_activos'] = 
            $balance['total_activo_circulante'] +
            $balance['total_activo_fijo'] +
            $balance['otros_activos'];

        $balance['total_pasivo_circulante'] = 
            $balance['pasivo_circulante_proveedores'] +
            $balance['pasivo_circulante_remuneraciones'] +
            $balance['pasivo_circulante_cotizaciones'] +
            $balance['pasivo_circulante_impuestos'] +
            $balance['pasivo_circulante_iva_debito'] +
            $balance['pasivo_circulante_arriendos_anticipados'] +
            $balance['pasivo_circulante_otros'];

        $balance['total_pasivo_largo_plazo'] = 
            $balance['pasivo_largo_plazo_deudas'] +
            $balance['pasivo_largo_plazo_provisiones'];

        $balance['total_pasivos'] = 
            $balance['total_pasivo_circulante'] +
            $balance['total_pasivo_largo_plazo'];

        $balance['total_patrimonio'] = 
            $balance['patrimonio_fondo_comun'] +
            $balance['patrimonio_fondo_reserva'] +
            $balance['patrimonio_resultados_acumulados'] +
            $balance['patrimonio_resultado_ejercicio'];

        $balance['total_pasivo_patrimonio'] = 
            $balance['total_pasivos'] +
            $balance['total_patrimonio'];

        // Verificar cuadratura
        $balance['diferencia'] = $balance['total_activos'] - $balance['total_pasivo_patrimonio'];
        $balance['cuadrado'] = abs($balance['diferencia']) < 0.01;

        // Guardar o actualizar
        $id = DB::table('balances_generales')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'edificio_id' => $edificioId,
                'anio_tributario' => $request->anio_tributario,
                'tipo' => $request->tipo,
                'mes' => $request->mes,
            ],
            $balance
        );

        return response()->json([
            'mensaje' => 'Balance General generado exitosamente',
            'balance' => $balance,
            'cuadrado' => $balance['cuadrado'],
            'diferencia' => $balance['diferencia'],
        ], 201);
    }

    /**
     * Descargar Balance General en PDF
     */
    public function descargarBalancePdf($id): mixed
    {
        $balance = DB::table('balances_generales')
            ->where('id', $id)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->first();

        if (!$balance) {
            return response()->json(['error' => 'Balance no encontrado'], 404);
        }

        $edificio = DB::table('edificios')->find($balance->edificio_id);

        $pdf = Pdf::loadView('pdf.balance-general', [
            'balance' => $balance,
            'edificio' => $edificio,
        ]);

        $filename = "balance-general-{$edificio->rut}-{$balance->anio_tributario}.pdf";
        return $pdf->download($filename);
    }

    // =========================================================================
    // ESTADO DE RESULTADOS
    // =========================================================================

    /**
     * Generar Estado de Resultados
     */
    public function generarEstadoResultados(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'anio_tributario' => 'required|integer',
            'tipo' => 'required|in:anual,mensual,trimestral',
            'fecha_inicio' => 'required|date',
            'fecha_cierre' => 'required|date',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $edificioId = $request->edificio_id;
        $fechaInicio = $request->fecha_inicio;
        $fechaCierre = $request->fecha_cierre;

        // Calcular ingresos desde boletas GC
        $ingresosGC = DB::table('boletas_gc')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->where('periodos_gc.edificio_id', $edificioId)
            ->whereBetween('boletas_gc.fecha_emision', [$fechaInicio, $fechaCierre])
            ->where('boletas_gc.estado', '!=', 'anulada')
            ->selectRaw('
                SUM(total_gastos_comunes) as gastos_comunes,
                SUM(total_fondo_reserva) as fondo_reserva,
                SUM(total_intereses) as multas_intereses
            ')
            ->first();

        // Calcular ingresos por arriendos
        $ingresosArriendos = DB::table('facturas_arriendo')
            ->join('contratos_arriendo', 'facturas_arriendo.contrato_id', '=', 'contratos_arriendo.id')
            ->where('contratos_arriendo.edificio_id', $edificioId)
            ->whereBetween('facturas_arriendo.fecha_emision', [$fechaInicio, $fechaCierre])
            ->where('facturas_arriendo.estado', '!=', 'anulada')
            ->selectRaw('
                SUM(CASE WHEN contratos_arriendo.tipo_espacio IN ("azotea", "sala_tecnica") THEN neto ELSE 0 END) as antenas,
                SUM(CASE WHEN contratos_arriendo.tipo_espacio = "fachada" THEN neto ELSE 0 END) as publicidad,
                SUM(CASE WHEN contratos_arriendo.tipo_espacio NOT IN ("azotea", "sala_tecnica", "fachada") THEN neto ELSE 0 END) as otros
            ')
            ->first();

        // Calcular gastos desde liquidaciones
        $gastosRRHH = DB::table('liquidaciones')
            ->join('empleados', 'liquidaciones.empleado_id', '=', 'empleados.id')
            ->where('empleados.edificio_id', $edificioId)
            ->whereYear('liquidaciones.created_at', '>=', Carbon::parse($fechaInicio)->year)
            ->whereYear('liquidaciones.created_at', '<=', Carbon::parse($fechaCierre)->year)
            ->selectRaw('
                SUM(sueldo_base + COALESCE(gratificacion, 0)) as remuneraciones,
                SUM(afp + salud + seguro_cesantia) as cotizaciones
            ')
            ->first();

        // Calcular distribución a copropietarios
        $distribucion = DB::table('distribuciones')
            ->where('edificio_id', $edificioId)
            ->whereYear('created_at', $request->anio_tributario)
            ->where('estado', 'aprobada')
            ->sum('monto_total');

        $eerr = [
            'tenant_id' => $tenantId,
            'edificio_id' => $edificioId,
            'anio_tributario' => $request->anio_tributario,
            'tipo' => $request->tipo,
            'mes' => $request->mes ?? null,
            'fecha_inicio' => $fechaInicio,
            'fecha_cierre' => $fechaCierre,

            // Ingresos operacionales
            'ingresos_gastos_comunes' => $ingresosGC->gastos_comunes ?? 0,
            'ingresos_fondo_reserva' => $ingresosGC->fondo_reserva ?? 0,
            'ingresos_multas_intereses' => $ingresosGC->multas_intereses ?? 0,
            'ingresos_arriendos_antenas' => $ingresosArriendos->antenas ?? 0,
            'ingresos_arriendos_publicidad' => $ingresosArriendos->publicidad ?? 0,
            'ingresos_arriendos_otros' => $ingresosArriendos->otros ?? 0,

            // Gastos operacionales
            'gastos_remuneraciones' => $gastosRRHH->remuneraciones ?? 0,
            'gastos_cotizaciones_previsionales' => $gastosRRHH->cotizaciones ?? 0,

            // Distribución
            'distribucion_copropietarios' => $distribucion,
            'monto_art_17_n3' => $distribucion, // Todo es Art. 17 N°3

            'estado' => 'generado',
            'generado_por' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Calcular totales
        $eerr['total_ingresos_operacionales'] = 
            $eerr['ingresos_gastos_comunes'] +
            $eerr['ingresos_fondo_reserva'] +
            $eerr['ingresos_multas_intereses'] +
            $eerr['ingresos_arriendos_antenas'] +
            $eerr['ingresos_arriendos_publicidad'] +
            $eerr['ingresos_arriendos_otros'];

        $eerr['total_gastos_operacionales'] = 
            $eerr['gastos_remuneraciones'] +
            $eerr['gastos_cotizaciones_previsionales'];

        $eerr['resultado_operacional'] = 
            $eerr['total_ingresos_operacionales'] - 
            $eerr['total_gastos_operacionales'];

        $eerr['resultado_antes_distribucion'] = $eerr['resultado_operacional'];
        $eerr['resultado_ejercicio'] = 
            $eerr['resultado_antes_distribucion'] - 
            $eerr['distribucion_copropietarios'];

        // Guardar
        DB::table('estados_resultados')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'edificio_id' => $edificioId,
                'anio_tributario' => $request->anio_tributario,
                'tipo' => $request->tipo,
            ],
            $eerr
        );

        return response()->json([
            'mensaje' => 'Estado de Resultados generado',
            'estado_resultados' => $eerr,
        ], 201);
    }

    /**
     * Descargar Estado de Resultados PDF
     */
    public function descargarEstadoResultadosPdf($id): mixed
    {
        $eerr = DB::table('estados_resultados')
            ->where('id', $id)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->first();

        if (!$eerr) {
            return response()->json(['error' => 'Estado de Resultados no encontrado'], 404);
        }

        $edificio = DB::table('edificios')->find($eerr->edificio_id);

        $pdf = Pdf::loadView('pdf.estado-resultados', [
            'eerr' => $eerr,
            'edificio' => $edificio,
        ]);

        return $pdf->download("estado-resultados-{$eerr->anio_tributario}.pdf");
    }

    // =========================================================================
    // DECLARACIÓN JURADA 1887
    // =========================================================================

    /**
     * Generar DJ 1887 - Rentas del Art. 42 N°1 y otros
     */
    public function generarDJ1887(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'anio_tributario' => 'required|integer',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $edificioId = $request->edificio_id;
        $anio = $request->anio_tributario;

        // Obtener todas las distribuciones del año
        $distribuciones = DB::table('distribucion_detalles')
            ->join('distribuciones', 'distribucion_detalles.distribucion_id', '=', 'distribuciones.id')
            ->join('personas', 'distribucion_detalles.persona_id', '=', 'personas.id')
            ->join('unidades', 'distribucion_detalles.unidad_id', '=', 'unidades.id')
            ->where('distribuciones.edificio_id', $edificioId)
            ->whereYear('distribuciones.created_at', $anio)
            ->where('distribuciones.estado', 'aprobada')
            ->select(
                'personas.rut',
                'personas.nombre_completo',
                'personas.direccion',
                'personas.comuna',
                'unidades.numero as unidad',
                DB::raw('SUM(distribucion_detalles.monto_neto) as monto_total')
            )
            ->groupBy('personas.id', 'personas.rut', 'personas.nombre_completo', 
                      'personas.direccion', 'personas.comuna', 'unidades.numero')
            ->get();

        $detalle = [];
        $montoTotalInformado = 0;

        foreach ($distribuciones as $dist) {
            $detalle[] = [
                'rut' => $dist->rut,
                'nombre' => $dist->nombre_completo,
                'direccion' => $dist->direccion,
                'comuna' => $dist->comuna,
                'unidad' => $dist->unidad,
                'monto_bruto' => $dist->monto_total,
                'monto_art_17_n3' => $dist->monto_total, // No constituye renta
                'monto_afecto' => 0,
                'retencion' => 0,
            ];
            $montoTotalInformado += $dist->monto_total;
        }

        $dj = [
            'tenant_id' => $tenantId,
            'edificio_id' => $edificioId,
            'tipo_dj' => 'DJ1887',
            'anio_tributario' => $anio,
            'numero_declaracion' => 'DJ1887-' . $anio . '-' . str_pad($edificioId, 5, '0', STR_PAD_LEFT),
            'fecha_generacion' => now()->toDateString(),
            'fecha_vencimiento' => "{$anio}-03-31", // Vence 31 de marzo
            'cantidad_informados' => count($detalle),
            'monto_total_informado' => $montoTotalInformado,
            'detalle' => json_encode($detalle),
            'resumen' => json_encode([
                'total_beneficiarios' => count($detalle),
                'monto_total' => $montoTotalInformado,
                'monto_art_17_n3' => $montoTotalInformado,
                'monto_afecto' => 0,
                'retenciones' => 0,
            ]),
            'estado' => 'generada',
            'generado_por' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('declaraciones_juradas')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'edificio_id' => $edificioId,
                'tipo_dj' => 'DJ1887',
                'anio_tributario' => $anio,
            ],
            $dj
        );

        return response()->json([
            'mensaje' => 'DJ 1887 generada exitosamente',
            'declaracion' => $dj,
            'detalle' => $detalle,
        ], 201);
    }

    /**
     * Descargar DJ 1887 en formato CSV (para subir a SII)
     */
    public function descargarDJ1887Csv($id): mixed
    {
        $dj = DB::table('declaraciones_juradas')
            ->where('id', $id)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->first();

        if (!$dj) {
            return response()->json(['error' => 'Declaración no encontrada'], 404);
        }

        $detalle = json_decode($dj->detalle, true);
        $edificio = DB::table('edificios')->find($dj->edificio_id);

        $csv = "RUT_INFORMANTE;RUT_BENEFICIARIO;NOMBRE_BENEFICIARIO;DIRECCION;COMUNA;MONTO_RENTA;MONTO_NO_RENTA;MONTO_AFECTO;RETENCION\n";

        foreach ($detalle as $item) {
            $csv .= implode(';', [
                $edificio->rut,
                $item['rut'],
                $item['nombre'],
                $item['direccion'] ?? '',
                $item['comuna'] ?? '',
                $item['monto_bruto'],
                $item['monto_art_17_n3'],
                $item['monto_afecto'],
                $item['retencion'],
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=DJ1887-{$dj->anio_tributario}.csv",
        ]);
    }

    // =========================================================================
    // REPORTE CONSOLIDADO DISTRIBUCIÓN
    // =========================================================================

    /**
     * Generar Reporte Consolidado de Distribución
     */
    public function generarReporteConsolidadoDistribucion(Request $request): JsonResponse
    {
        $request->validate([
            'edificio_id' => 'required|exists:edificios,id',
            'anio' => 'required|integer',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $edificioId = $request->edificio_id;
        $anio = $request->anio;

        // Ingresos por arriendos detallados
        $ingresosPorTipo = DB::table('facturas_arriendo')
            ->join('contratos_arriendo', 'facturas_arriendo.contrato_id', '=', 'contratos_arriendo.id')
            ->where('contratos_arriendo.edificio_id', $edificioId)
            ->whereYear('facturas_arriendo.fecha_emision', $anio)
            ->where('facturas_arriendo.estado', 'pagada')
            ->selectRaw("
                contratos_arriendo.tipo_espacio,
                SUM(facturas_arriendo.neto) as total_neto,
                SUM(facturas_arriendo.iva) as total_iva,
                SUM(facturas_arriendo.total) as total_bruto,
                COUNT(*) as cantidad_facturas
            ")
            ->groupBy('contratos_arriendo.tipo_espacio')
            ->get();

        $totalAntenas = 0;
        $totalPublicidad = 0;
        $totalEspacios = 0;
        $totalOtros = 0;
        $detallePorTipo = [];

        foreach ($ingresosPorTipo as $ing) {
            $detallePorTipo[$ing->tipo_espacio] = [
                'neto' => $ing->total_neto,
                'iva' => $ing->total_iva,
                'bruto' => $ing->total_bruto,
                'facturas' => $ing->cantidad_facturas,
            ];

            if (in_array($ing->tipo_espacio, ['azotea', 'sala_tecnica'])) {
                $totalAntenas += $ing->total_neto;
            } elseif ($ing->tipo_espacio === 'fachada') {
                $totalPublicidad += $ing->total_neto;
            } elseif (in_array($ing->tipo_espacio, ['subterraneo', 'terreno'])) {
                $totalEspacios += $ing->total_neto;
            } else {
                $totalOtros += $ing->total_neto;
            }
        }

        // Ingresos por arrendatario
        $ingresosPorArrendatario = DB::table('facturas_arriendo')
            ->join('contratos_arriendo', 'facturas_arriendo.contrato_id', '=', 'contratos_arriendo.id')
            ->join('arrendatarios', 'contratos_arriendo.arrendatario_id', '=', 'arrendatarios.id')
            ->where('contratos_arriendo.edificio_id', $edificioId)
            ->whereYear('facturas_arriendo.fecha_emision', $anio)
            ->where('facturas_arriendo.estado', 'pagada')
            ->selectRaw("
                arrendatarios.razon_social,
                arrendatarios.rut,
                SUM(facturas_arriendo.neto) as total,
                COUNT(*) as facturas
            ")
            ->groupBy('arrendatarios.id', 'arrendatarios.razon_social', 'arrendatarios.rut')
            ->get();

        // Resumen mensual de ingresos
        $resumenMensual = DB::table('facturas_arriendo')
            ->join('contratos_arriendo', 'facturas_arriendo.contrato_id', '=', 'contratos_arriendo.id')
            ->where('contratos_arriendo.edificio_id', $edificioId)
            ->whereYear('facturas_arriendo.fecha_emision', $anio)
            ->where('facturas_arriendo.estado', 'pagada')
            ->selectRaw("
                MONTH(facturas_arriendo.fecha_emision) as mes,
                SUM(facturas_arriendo.neto) as total
            ")
            ->groupBy(DB::raw('MONTH(facturas_arriendo.fecha_emision)'))
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');

        // Total distribuido
        $totalDistribuido = DB::table('distribuciones')
            ->where('edificio_id', $edificioId)
            ->whereYear('created_at', $anio)
            ->where('estado', 'aprobada')
            ->sum('monto_total');

        // Contar beneficiarios
        $beneficiarios = DB::table('distribucion_detalles')
            ->join('distribuciones', 'distribucion_detalles.distribucion_id', '=', 'distribuciones.id')
            ->where('distribuciones.edificio_id', $edificioId)
            ->whereYear('distribuciones.created_at', $anio)
            ->where('distribuciones.estado', 'aprobada')
            ->selectRaw('COUNT(DISTINCT unidad_id) as unidades, COUNT(DISTINCT persona_id) as personas')
            ->first();

        $totalIngresosBrutos = $totalAntenas + $totalPublicidad + $totalEspacios + $totalOtros;
        $excedente = $totalIngresosBrutos - $totalDistribuido;

        $numeroReporte = 'RDC-' . $anio . '-' . str_pad($edificioId, 5, '0', STR_PAD_LEFT);

        $reporte = [
            'tenant_id' => $tenantId,
            'edificio_id' => $edificioId,
            'anio' => $anio,
            'numero_reporte' => $numeroReporte,
            'total_arriendos_antenas' => $totalAntenas,
            'total_arriendos_publicidad' => $totalPublicidad,
            'total_arriendos_espacios' => $totalEspacios,
            'total_otros_ingresos' => $totalOtros,
            'total_ingresos_brutos' => $totalIngresosBrutos,
            'gastos_asociados' => 0,
            'total_neto_distribuible' => $totalIngresosBrutos,
            'total_distribuido' => $totalDistribuido,
            'excedente_no_distribuido' => $excedente,
            'cantidad_unidades_beneficiarias' => $beneficiarios->unidades ?? 0,
            'cantidad_copropietarios_beneficiarios' => $beneficiarios->personas ?? 0,
            'cantidad_distribuciones' => DB::table('distribuciones')
                ->where('edificio_id', $edificioId)
                ->whereYear('created_at', $anio)
                ->where('estado', 'aprobada')
                ->count(),
            'detalle_por_tipo_ingreso' => json_encode($detallePorTipo),
            'detalle_por_arrendatario' => json_encode($ingresosPorArrendatario),
            'resumen_mensual' => json_encode($resumenMensual),
            'estado' => 'generado',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $reporteId = DB::table('reportes_distribucion_consolidado')->insertGetId($reporte);

        // Generar detalle por contribuyente
        $this->generarDetalleContribuyentes($tenantId, $reporteId, $edificioId, $anio);

        return response()->json([
            'mensaje' => 'Reporte consolidado generado',
            'reporte' => $reporte,
            'detalle_por_tipo' => $detallePorTipo,
            'detalle_por_arrendatario' => $ingresosPorArrendatario,
        ], 201);
    }

    /**
     * Generar detalle por contribuyente
     */
    private function generarDetalleContribuyentes($tenantId, $reporteId, $edificioId, $anio): void
    {
        // Obtener todas las distribuciones del año agrupadas por persona/unidad
        $distribuciones = DB::table('distribucion_detalles')
            ->join('distribuciones', 'distribucion_detalles.distribucion_id', '=', 'distribuciones.id')
            ->join('personas', 'distribucion_detalles.persona_id', '=', 'personas.id')
            ->join('unidades', 'distribucion_detalles.unidad_id', '=', 'unidades.id')
            ->where('distribuciones.edificio_id', $edificioId)
            ->whereYear('distribuciones.created_at', $anio)
            ->where('distribuciones.estado', 'aprobada')
            ->select(
                'distribucion_detalles.*',
                'distribuciones.mes',
                'distribuciones.anio',
                'personas.rut as rut_persona',
                'personas.nombre_completo',
                'personas.direccion as direccion_persona',
                'personas.email',
                'unidades.numero as numero_unidad',
                'unidades.tipo as tipo_unidad',
                'unidades.rol_avaluo',
                'unidades.prorrateo'
            )
            ->orderBy('personas.rut')
            ->orderBy('distribuciones.mes')
            ->get();

        // Agrupar por persona + unidad
        $agrupado = $distribuciones->groupBy(function ($item) {
            return $item->persona_id . '-' . $item->unidad_id;
        });

        foreach ($agrupado as $key => $items) {
            $primer = $items->first();
            
            $detalleMensual = [];
            $totalDistribuido = 0;

            foreach ($items as $item) {
                $detalleMensual[] = [
                    'mes' => $item->mes,
                    'monto_bruto' => $item->monto_bruto ?? $item->monto_neto,
                    'distribuido' => $item->monto_neto,
                    'fecha_pago' => $item->fecha_pago,
                ];
                $totalDistribuido += $item->monto_neto;
            }

            $codigoVerificacion = strtoupper(Str::random(12));

            DB::table('distribucion_detalle_contribuyente')->insert([
                'tenant_id' => $tenantId,
                'reporte_consolidado_id' => $reporteId,
                'edificio_id' => $edificioId,
                'unidad_id' => $primer->unidad_id,
                'persona_id' => $primer->persona_id,
                'anio' => $anio,
                'rut_contribuyente' => $primer->rut_persona,
                'nombre_contribuyente' => $primer->nombre_completo,
                'direccion_contribuyente' => $primer->direccion_persona,
                'email_contribuyente' => $primer->email,
                'numero_unidad' => $primer->numero_unidad,
                'tipo_unidad' => $primer->tipo_unidad,
                'rol_avaluo' => $primer->rol_avaluo,
                'prorrateo' => $primer->prorrateo,
                'total_ingresos_brutos' => $totalDistribuido,
                'total_distribuido' => $totalDistribuido,
                'monto_art_17_n3' => $totalDistribuido,
                'monto_afecto_impuesto' => 0,
                'retenciones' => 0,
                'detalle_mensual' => json_encode($detalleMensual),
                'cantidad_pagos' => count($detalleMensual),
                'codigo_verificacion' => $codigoVerificacion,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Obtener detalle de distribución por contribuyente
     */
    public function obtenerDetalleContribuyente(Request $request, $personaId): JsonResponse
    {
        $request->validate([
            'anio' => 'required|integer',
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Detalle por cada propiedad
        $detallePropiedades = DB::table('distribucion_detalle_contribuyente')
            ->join('edificios', 'distribucion_detalle_contribuyente.edificio_id', '=', 'edificios.id')
            ->where('distribucion_detalle_contribuyente.tenant_id', $tenantId)
            ->where('distribucion_detalle_contribuyente.persona_id', $personaId)
            ->where('distribucion_detalle_contribuyente.anio', $request->anio)
            ->select(
                'distribucion_detalle_contribuyente.*',
                'edificios.nombre as edificio_nombre',
                'edificios.direccion as edificio_direccion'
            )
            ->get();

        if ($detallePropiedades->isEmpty()) {
            return response()->json(['error' => 'No se encontraron datos'], 404);
        }

        // Calcular consolidado
        $persona = DB::table('personas')->find($personaId);
        
        $consolidado = [
            'rut' => $persona->rut,
            'nombre' => $persona->nombre_completo,
            'anio' => $request->anio,
            'cantidad_propiedades' => $detallePropiedades->count(),
            'total_distribuido' => $detallePropiedades->sum('total_distribuido'),
            'total_art_17_n3' => $detallePropiedades->sum('monto_art_17_n3'),
            'total_afecto' => $detallePropiedades->sum('monto_afecto_impuesto'),
            'total_retenciones' => $detallePropiedades->sum('retenciones'),
        ];

        return response()->json([
            'persona' => $persona,
            'consolidado' => $consolidado,
            'detalle_propiedades' => $detallePropiedades,
        ]);
    }

    /**
     * Descargar certificado individual por propiedad
     */
    public function descargarCertificadoIndividual($detalleId): mixed
    {
        $detalle = DB::table('distribucion_detalle_contribuyente')
            ->where('id', $detalleId)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->first();

        if (!$detalle) {
            return response()->json(['error' => 'Detalle no encontrado'], 404);
        }

        $edificio = DB::table('edificios')->find($detalle->edificio_id);
        $persona = DB::table('personas')->find($detalle->persona_id);

        $pdf = Pdf::loadView('pdf.certificado-renta-individual', [
            'detalle' => $detalle,
            'edificio' => $edificio,
            'persona' => $persona,
            'detalle_mensual' => json_decode($detalle->detalle_mensual, true),
        ]);

        return $pdf->download("certificado-renta-{$detalle->rut_contribuyente}-{$detalle->numero_unidad}-{$detalle->anio}.pdf");
    }

    /**
     * Descargar certificado consolidado (todas las propiedades)
     */
    public function descargarCertificadoConsolidado(Request $request, $personaId): mixed
    {
        $request->validate(['anio' => 'required|integer']);

        $tenantId = auth()->user()->tenant_id;
        $anio = $request->anio;

        $persona = DB::table('personas')->find($personaId);
        
        $propiedades = DB::table('distribucion_detalle_contribuyente')
            ->join('edificios', 'distribucion_detalle_contribuyente.edificio_id', '=', 'edificios.id')
            ->where('distribucion_detalle_contribuyente.tenant_id', $tenantId)
            ->where('distribucion_detalle_contribuyente.persona_id', $personaId)
            ->where('distribucion_detalle_contribuyente.anio', $anio)
            ->select('distribucion_detalle_contribuyente.*', 'edificios.nombre as edificio_nombre')
            ->get();

        if ($propiedades->isEmpty()) {
            return response()->json(['error' => 'Sin datos para el período'], 404);
        }

        $consolidado = [
            'cantidad_propiedades' => $propiedades->count(),
            'total_distribuido' => $propiedades->sum('total_distribuido'),
            'total_art_17_n3' => $propiedades->sum('monto_art_17_n3'),
        ];

        $codigoVerificacion = strtoupper(Str::random(16));

        $pdf = Pdf::loadView('pdf.certificado-renta-consolidado', [
            'persona' => $persona,
            'anio' => $anio,
            'propiedades' => $propiedades,
            'consolidado' => $consolidado,
            'codigo_verificacion' => $codigoVerificacion,
            'fecha_emision' => now()->format('d/m/Y'),
        ]);

        return $pdf->download("certificado-renta-consolidado-{$persona->rut}-{$anio}.pdf");
    }

    // =========================================================================
    // CERTIFICADOS DE NO DEUDA / PAGO GGCC
    // =========================================================================

    /**
     * Generar certificado de no deuda o estado de cuenta
     */
    public function generarCertificadoDeuda(Request $request): JsonResponse
    {
        $request->validate([
            'unidad_id' => 'required|exists:unidades,id',
            'tipo' => 'required|in:no_deuda,pago_al_dia,estado_cuenta,deuda_pendiente',
            'motivo_solicitud' => 'nullable|string|max:100',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $unidad = DB::table('unidades')->find($request->unidad_id);
        $edificio = DB::table('edificios')->find($unidad->edificio_id);

        // Calcular estado de deuda
        $fechaCorte = now()->toDateString();
        
        $boletasPendientes = DB::table('boletas_gc')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->where('boletas_gc.unidad_id', $request->unidad_id)
            ->whereIn('boletas_gc.estado', ['pendiente', 'parcial'])
            ->where('boletas_gc.fecha_vencimiento', '<=', $fechaCorte)
            ->selectRaw('
                SUM(boletas_gc.total_gastos_comunes - COALESCE(boletas_gc.total_pagado, 0)) as deuda_gc,
                SUM(boletas_gc.total_fondo_reserva) as deuda_fondo,
                SUM(boletas_gc.total_intereses) as deuda_intereses
            ')
            ->first();

        $deudaGC = $boletasPendientes->deuda_gc ?? 0;
        $deudaFondo = $boletasPendientes->deuda_fondo ?? 0;
        $deudaIntereses = $boletasPendientes->deuda_intereses ?? 0;
        $deudaTotal = $deudaGC + $deudaFondo + $deudaIntereses;

        $tieneDeuda = $deudaTotal > 0;

        // Verificar tipo solicitado vs realidad
        if ($request->tipo === 'no_deuda' && $tieneDeuda) {
            return response()->json([
                'error' => 'No se puede emitir certificado de NO DEUDA. La unidad tiene deuda pendiente.',
                'deuda_total' => $deudaTotal,
                'detalle' => [
                    'gastos_comunes' => $deudaGC,
                    'fondo_reserva' => $deudaFondo,
                    'intereses' => $deudaIntereses,
                ]
            ], 422);
        }

        // Último pago
        $ultimoPago = DB::table('pagos_gc')
            ->join('boletas_gc', 'pagos_gc.boleta_id', '=', 'boletas_gc.id')
            ->where('boletas_gc.unidad_id', $request->unidad_id)
            ->where('pagos_gc.anulado', false)
            ->orderByDesc('pagos_gc.fecha_pago')
            ->first();

        // Detalle de períodos
        $periodos = DB::table('boletas_gc')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->where('boletas_gc.unidad_id', $request->unidad_id)
            ->orderByDesc('periodos_gc.anio')
            ->orderByDesc('periodos_gc.mes')
            ->limit(24)
            ->select(
                'periodos_gc.mes',
                'periodos_gc.anio',
                'boletas_gc.estado',
                'boletas_gc.total_a_pagar',
                'boletas_gc.total_pagado'
            )
            ->get()
            ->map(function ($p) {
                return [
                    'periodo' => "{$p->anio}-" . str_pad($p->mes, 2, '0', STR_PAD_LEFT),
                    'estado' => $p->estado,
                    'monto' => $p->total_a_pagar,
                    'pagado' => $p->total_pagado,
                ];
            });

        $codigoVerificacion = strtoupper(Str::random(12));
        $numeroCertificado = 'CD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $certificado = [
            'tenant_id' => $tenantId,
            'edificio_id' => $edificio->id,
            'unidad_id' => $request->unidad_id,
            'numero_certificado' => $numeroCertificado,
            'tipo' => $tieneDeuda ? 'deuda_pendiente' : $request->tipo,
            'fecha_emision' => now()->toDateString(),
            'fecha_validez' => now()->addDays(30)->toDateString(),
            'fecha_corte' => $fechaCorte,
            'tiene_deuda' => $tieneDeuda,
            'deuda_gastos_comunes' => $deudaGC,
            'deuda_fondo_reserva' => $deudaFondo,
            'deuda_intereses' => $deudaIntereses,
            'deuda_total' => $deudaTotal,
            'fecha_ultimo_pago' => $ultimoPago->fecha_pago ?? null,
            'monto_ultimo_pago' => $ultimoPago->monto ?? null,
            'detalle_periodos' => json_encode($periodos),
            'codigo_verificacion' => $codigoVerificacion,
            'motivo_solicitud' => $request->motivo_solicitud,
            'emitido_por' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('certificados_deuda')->insertGetId($certificado);

        // Registrar en historial
        $copropietario = DB::table('copropietarios')
            ->where('unidad_id', $request->unidad_id)
            ->where('principal', true)
            ->first();

        if ($copropietario) {
            DB::table('historial_certificados_tributarios')->insert([
                'tenant_id' => $tenantId,
                'edificio_id' => $edificio->id,
                'unidad_id' => $request->unidad_id,
                'persona_id' => $copropietario->persona_id,
                'tipo_certificado' => $tieneDeuda ? 'deuda_pendiente' : $request->tipo,
                'numero_certificado' => $numeroCertificado,
                'codigo_verificacion' => $codigoVerificacion,
                'fecha_emision' => now()->toDateString(),
                'fecha_validez' => now()->addDays(30)->toDateString(),
                'monto_principal' => $deudaTotal,
                'emitido_por' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'mensaje' => 'Certificado generado exitosamente',
            'certificado' => $certificado,
            'id' => $id,
        ], 201);
    }

    /**
     * Descargar certificado de deuda/no deuda en PDF
     */
    public function descargarCertificadoDeudaPdf($id): mixed
    {
        $certificado = DB::table('certificados_deuda')
            ->where('id', $id)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->first();

        if (!$certificado) {
            return response()->json(['error' => 'Certificado no encontrado'], 404);
        }

        $edificio = DB::table('edificios')->find($certificado->edificio_id);
        $unidad = DB::table('unidades')->find($certificado->unidad_id);
        $copropietario = DB::table('copropietarios')
            ->join('personas', 'copropietarios.persona_id', '=', 'personas.id')
            ->where('copropietarios.unidad_id', $certificado->unidad_id)
            ->where('copropietarios.principal', true)
            ->select('personas.*')
            ->first();

        $pdf = Pdf::loadView('pdf.certificado-deuda', [
            'certificado' => $certificado,
            'edificio' => $edificio,
            'unidad' => $unidad,
            'copropietario' => $copropietario,
            'periodos' => json_decode($certificado->detalle_periodos, true),
        ]);

        $tipoNombre = match($certificado->tipo) {
            'no_deuda' => 'no-deuda',
            'pago_al_dia' => 'pago-al-dia',
            'deuda_pendiente' => 'estado-deuda',
            default => 'certificado'
        };

        return $pdf->download("certificado-{$tipoNombre}-{$unidad->numero}.pdf");
    }

    /**
     * Verificar certificado (endpoint público)
     */
    public function verificarCertificado($codigo): JsonResponse
    {
        $certificado = DB::table('certificados_deuda')
            ->where('codigo_verificacion', $codigo)
            ->first();

        if (!$certificado) {
            // Buscar en certificados tributarios
            $certTributario = DB::table('historial_certificados_tributarios')
                ->where('codigo_verificacion', $codigo)
                ->first();

            if (!$certTributario) {
                return response()->json([
                    'valido' => false,
                    'mensaje' => 'Certificado no encontrado'
                ], 404);
            }

            return response()->json([
                'valido' => true,
                'tipo' => $certTributario->tipo_certificado,
                'numero' => $certTributario->numero_certificado,
                'fecha_emision' => $certTributario->fecha_emision,
                'fecha_validez' => $certTributario->fecha_validez,
                'vigente' => $certTributario->fecha_validez >= now()->toDateString(),
            ]);
        }

        $edificio = DB::table('edificios')->find($certificado->edificio_id);
        $unidad = DB::table('unidades')->find($certificado->unidad_id);

        return response()->json([
            'valido' => true,
            'tipo' => $certificado->tipo,
            'numero' => $certificado->numero_certificado,
            'edificio' => $edificio->nombre,
            'unidad' => $unidad->numero,
            'fecha_emision' => $certificado->fecha_emision,
            'fecha_validez' => $certificado->fecha_validez,
            'vigente' => $certificado->fecha_validez >= now()->toDateString(),
            'tiene_deuda' => (bool) $certificado->tiene_deuda,
            'deuda_total' => $certificado->deuda_total,
        ]);
    }

    // =========================================================================
    // CHECKLIST CUMPLIMIENTO LEGAL
    // =========================================================================

    /**
     * Generar checklist de cumplimiento por unidad
     */
    public function generarChecklistCumplimiento(Request $request): JsonResponse
    {
        $request->validate([
            'unidad_id' => 'required|exists:unidades,id',
            'anio' => 'required|integer',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $unidadId = $request->unidad_id;
        $anio = $request->anio;

        $unidad = DB::table('unidades')->find($unidadId);
        $edificio = DB::table('edificios')->find($unidad->edificio_id);

        // Verificar GASTOS COMUNES
        $deudaGC = DB::table('boletas_gc')
            ->where('unidad_id', $unidadId)
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->sum(DB::raw('total_a_pagar - COALESCE(total_pagado, 0)'));

        $gcPagosAlDia = $deudaGC <= 0;
        $gcSinDeudaHistorica = DB::table('boletas_gc')
            ->where('unidad_id', $unidadId)
            ->where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<', now()->subMonths(3))
            ->doesntExist();

        // Verificar DISTRIBUCIÓN
        $recibioDist = DB::table('distribucion_detalles')
            ->join('distribuciones', 'distribucion_detalles.distribucion_id', '=', 'distribuciones.id')
            ->where('distribucion_detalles.unidad_id', $unidadId)
            ->whereYear('distribuciones.created_at', $anio)
            ->exists();

        $certEmitido = DB::table('distribucion_detalle_contribuyente')
            ->where('unidad_id', $unidadId)
            ->where('anio', $anio)
            ->whereNotNull('codigo_verificacion')
            ->exists();

        // Verificar DATOS COPROPIETARIO
        $copropietario = DB::table('copropietarios')
            ->join('personas', 'copropietarios.persona_id', '=', 'personas.id')
            ->where('copropietarios.unidad_id', $unidadId)
            ->where('copropietarios.principal', true)
            ->select('personas.*')
            ->first();

        $datosActualizados = $copropietario && 
            !empty($copropietario->email) && 
            !empty($copropietario->telefono);

        $aceptoPoliticaDatos = $copropietario && 
            $copropietario->acepta_politica_privacidad;

        // Calcular totales
        $items = [
            'gc_pagos_al_dia' => $gcPagosAlDia,
            'gc_sin_deuda_historica' => $gcSinDeudaHistorica,
            'gc_fondo_reserva_pagado' => $gcPagosAlDia, // Incluido en GC
            'dist_recibio_distribucion' => $recibioDist,
            'dist_certificado_emitido' => $certEmitido,
            'dist_datos_bancarios_actualizados' => $datosActualizados,
            'legal_datos_propietario_actualizados' => $datosActualizados,
            'legal_email_verificado' => !empty($copropietario->email ?? null),
            'legal_acepto_politica_datos' => $aceptoPoliticaDatos,
            'ley21442_prorrateo_actualizado' => $unidad->prorrateo > 0,
            'datos_consentimiento_vigente' => $aceptoPoliticaDatos,
        ];

        $totalItems = count($items);
        $itemsCumplidos = count(array_filter($items));
        $porcentaje = round(($itemsCumplidos / $totalItems) * 100, 2);

        $estadoGeneral = match(true) {
            $porcentaje >= 90 => 'cumple',
            $porcentaje >= 60 => 'cumple_parcial',
            default => 'no_cumple'
        };

        $alertas = [];
        if (!$gcPagosAlDia) $alertas[] = 'Tiene deuda de gastos comunes pendiente';
        if (!$datosActualizados) $alertas[] = 'Datos del copropietario incompletos';
        if (!$aceptoPoliticaDatos) $alertas[] = 'No ha aceptado política de privacidad';
        if (!$certEmitido && $recibioDist) $alertas[] = 'Falta emitir certificado de renta';

        $checklist = array_merge($items, [
            'tenant_id' => $tenantId,
            'edificio_id' => $edificio->id,
            'unidad_id' => $unidadId,
            'anio' => $anio,
            'fecha_revision' => now()->toDateString(),
            'total_items_evaluados' => $totalItems,
            'items_cumplidos' => $itemsCumplidos,
            'porcentaje_cumplimiento' => $porcentaje,
            'estado_general' => $estadoGeneral,
            'alertas' => json_encode($alertas),
            'revisado_por' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('checklist_cumplimiento_unidad')->updateOrInsert(
            ['unidad_id' => $unidadId, 'anio' => $anio],
            $checklist
        );

        return response()->json([
            'mensaje' => 'Checklist generado',
            'checklist' => $checklist,
            'alertas' => $alertas,
            'porcentaje' => $porcentaje,
            'estado' => $estadoGeneral,
        ]);
    }

    /**
     * Obtener checklist de todas las unidades de un edificio
     */
    public function checklistEdificio(Request $request, $edificioId): JsonResponse
    {
        $request->validate(['anio' => 'required|integer']);

        $checklists = DB::table('checklist_cumplimiento_unidad')
            ->join('unidades', 'checklist_cumplimiento_unidad.unidad_id', '=', 'unidades.id')
            ->where('checklist_cumplimiento_unidad.edificio_id', $edificioId)
            ->where('checklist_cumplimiento_unidad.anio', $request->anio)
            ->select(
                'unidades.numero',
                'unidades.tipo',
                'checklist_cumplimiento_unidad.*'
            )
            ->orderBy('unidades.numero')
            ->get();

        $resumen = [
            'total_unidades' => $checklists->count(),
            'cumplen' => $checklists->where('estado_general', 'cumple')->count(),
            'cumplen_parcial' => $checklists->where('estado_general', 'cumple_parcial')->count(),
            'no_cumplen' => $checklists->where('estado_general', 'no_cumple')->count(),
            'promedio_cumplimiento' => round($checklists->avg('porcentaje_cumplimiento'), 2),
        ];

        return response()->json([
            'resumen' => $resumen,
            'checklists' => $checklists,
        ]);
    }

    // =========================================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // =========================================================================

    private function calcularSaldosCuentas($tenantId, $edificioId, $fechaCierre): array
    {
        $saldos = DB::table('asiento_lineas')
            ->join('asientos', 'asiento_lineas.asiento_id', '=', 'asientos.id')
            ->join('plan_cuentas', 'asiento_lineas.cuenta_id', '=', 'plan_cuentas.id')
            ->where('asientos.tenant_id', $tenantId)
            ->where('asientos.edificio_id', $edificioId)
            ->where('asientos.fecha', '<=', $fechaCierre)
            ->where('asientos.estado', 'contabilizado')
            ->selectRaw('
                plan_cuentas.codigo,
                SUM(asiento_lineas.debe) as total_debe,
                SUM(asiento_lineas.haber) as total_haber
            ')
            ->groupBy('plan_cuentas.codigo')
            ->get();

        $resultado = [];
        foreach ($saldos as $s) {
            // Activos y Gastos: saldo deudor (Debe - Haber)
            // Pasivos, Patrimonio, Ingresos: saldo acreedor (Haber - Debe)
            $primerDigito = substr($s->codigo, 0, 1);
            if (in_array($primerDigito, ['1', '5'])) {
                $resultado[$s->codigo] = $s->total_debe - $s->total_haber;
            } else {
                $resultado[$s->codigo] = $s->total_haber - $s->total_debe;
            }
        }

        return $resultado;
    }

    private function calcularDeudoresGC($tenantId, $edificioId, $fechaCierre): float
    {
        return DB::table('boletas_gc')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->where('periodos_gc.edificio_id', $edificioId)
            ->where('boletas_gc.fecha_emision', '<=', $fechaCierre)
            ->whereIn('boletas_gc.estado', ['pendiente', 'parcial'])
            ->sum(DB::raw('boletas_gc.total_a_pagar - COALESCE(boletas_gc.total_pagado, 0)'));
    }

    private function calcularArriendosPorCobrar($tenantId, $edificioId, $fechaCierre): float
    {
        return DB::table('facturas_arriendo')
            ->join('contratos_arriendo', 'facturas_arriendo.contrato_id', '=', 'contratos_arriendo.id')
            ->where('contratos_arriendo.edificio_id', $edificioId)
            ->where('facturas_arriendo.fecha_emision', '<=', $fechaCierre)
            ->whereIn('facturas_arriendo.estado', ['emitida', 'vencida'])
            ->sum('facturas_arriendo.total');
    }

    private function calcularFondoReserva($tenantId, $edificioId, $fechaCierre): float
    {
        $ingresos = DB::table('boletas_gc')
            ->join('periodos_gc', 'boletas_gc.periodo_id', '=', 'periodos_gc.id')
            ->where('periodos_gc.edificio_id', $edificioId)
            ->where('boletas_gc.fecha_emision', '<=', $fechaCierre)
            ->where('boletas_gc.estado', 'pagada')
            ->sum('boletas_gc.total_fondo_reserva');

        // Restar gastos del fondo de reserva si los hay
        return $ingresos;
    }
}
