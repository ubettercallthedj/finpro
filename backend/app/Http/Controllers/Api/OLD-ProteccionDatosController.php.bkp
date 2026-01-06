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
