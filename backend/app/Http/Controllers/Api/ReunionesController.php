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
