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
