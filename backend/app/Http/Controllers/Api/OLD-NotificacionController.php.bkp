<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    /**
     * Listar todas las notificaciones del usuario
     */
    public function index(Request $request): JsonResponse
    {
        $notificaciones = DB::table('notificaciones')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($notificaciones);
    }

    /**
     * Listar notificaciones no leídas
     */
    public function noLeidas(): JsonResponse
    {
        $notificaciones = DB::table('notificaciones')
            ->where('user_id', Auth::id())
            ->where('leida', false)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'notificaciones' => $notificaciones,
            'total_no_leidas' => $notificaciones->count(),
        ]);
    }

    /**
     * Marcar una notificación como leída
     */
    public function marcarLeida(int $id): JsonResponse
    {
        $notificacion = DB::table('notificaciones')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$notificacion) {
            return response()->json(['message' => 'Notificación no encontrada'], 404);
        }

        DB::table('notificaciones')
            ->where('id', $id)
            ->update([
                'leida' => true,
                'leida_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Notificación marcada como leída']);
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasLeidas(): JsonResponse
    {
        $actualizadas = DB::table('notificaciones')
            ->where('user_id', Auth::id())
            ->where('leida', false)
            ->update([
                'leida' => true,
                'leida_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Todas las notificaciones marcadas como leídas',
            'actualizadas' => $actualizadas
        ]);
    }

    /**
     * Eliminar una notificación
     */
    public function destroy(int $id): JsonResponse
    {
        $eliminadas = DB::table('notificaciones')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->delete();

        if ($eliminadas === 0) {
            return response()->json(['message' => 'Notificación no encontrada'], 404);
        }

        return response()->json(['message' => 'Notificación eliminada']);
    }

    /**
     * Obtener estadísticas de notificaciones
     */
    public function estadisticas(): JsonResponse
    {
        $stats = DB::table('notificaciones')
            ->where('user_id', Auth::id())
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN leida = 0 THEN 1 ELSE 0 END) as no_leidas,
                SUM(CASE WHEN leida = 1 THEN 1 ELSE 0 END) as leidas
            ')
            ->first();

        $porTipo = DB::table('notificaciones')
            ->where('user_id', Auth::id())
            ->where('leida', false)
            ->select('tipo', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('tipo')
            ->get();

        return response()->json([
            'total' => $stats->total,
            'no_leidas' => $stats->no_leidas,
            'leidas' => $stats->leidas,
            'por_tipo' => $porTipo,
        ]);
    }
}
