<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

        $user = DB::table('users')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        if (!$user->activo) {
            return response()->json(['message' => 'Usuario desactivado'], 403);
        }

        DB::table('users')->where('id', $user->id)->update(['ultimo_login' => now()]);

        $token = Str::random(64);
        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
            'token' => hash('sha256', $token),
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
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
