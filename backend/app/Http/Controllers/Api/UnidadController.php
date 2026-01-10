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
