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

        $user = User::where('email', $request->email)->first();

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

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
            ],
            'token' => $token,
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
