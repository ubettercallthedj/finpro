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
