<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EdificioController;
use App\Http\Controllers\Api\UnidadController;
use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\GastosComunesController;
use App\Http\Controllers\Api\ArriendosController;
use App\Http\Controllers\Api\DistribucionController;
use App\Http\Controllers\Api\RRHHController;
use App\Http\Controllers\Api\ContabilidadController;
use App\Http\Controllers\Api\ReunionesController;
use App\Http\Controllers\Api\AsistenteLegalController;
use App\Http\Controllers\Api\ProteccionDatosController;
use App\Http\Controllers\Api\ReportesTributariosController;

// Health config by Cloud and SCE

Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

// Health check
Route::get('/health', fn() => response()->json([
    'status' => 'ok', 
    'timestamp' => now(),
    'app' => 'DATAPOLIS PRO',
    'version' => '1.0.0'
]));

// ========================================
// AUTH ROUTES (Sin autenticación)
// ========================================
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    
    // Rutas autenticadas
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });
});

// ========================================
// RUTAS PROTEGIDAS
// ========================================
Route::middleware('auth:sanctum')->group(function () {
    
    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/morosidad', [DashboardController::class, 'morosidad']);
        Route::get('/ingresos', [DashboardController::class, 'ingresos']);
        Route::get('/alertas', [DashboardController::class, 'alertas']);
    });

    // Edificios
    Route::apiResource('edificios', EdificioController::class);
    Route::get('edificios/{id}/unidades', [EdificioController::class, 'unidades']);
    Route::get('edificios/{id}/estadisticas', [EdificioController::class, 'estadisticas']);

    // Unidades
    Route::apiResource('unidades', UnidadController::class);
    Route::get('unidades/{id}/boletas', [UnidadController::class, 'boletas']);
    Route::get('unidades/{id}/estado-cuenta', [UnidadController::class, 'estadoCuenta']);

    // Personas
    Route::apiResource('personas', PersonaController::class);

    // Gastos Comunes
    Route::prefix('gastos-comunes')->group(function () {
        Route::get('/periodos', [GastosComunesController::class, 'periodos']);
        Route::post('/periodos', [GastosComunesController::class, 'crearPeriodo']);
        Route::get('/periodos/{id}', [GastosComunesController::class, 'showPeriodo']);
        Route::post('/periodos/{id}/generar-boletas', [GastosComunesController::class, 'generarBoletas']);
        Route::post('/periodos/{id}/cerrar', [GastosComunesController::class, 'cerrarPeriodo']);
        
        Route::get('/boletas', [GastosComunesController::class, 'boletas']);
        Route::get('/boletas/{id}', [GastosComunesController::class, 'showBoleta']);
        Route::get('/boletas/{id}/pdf', [GastosComunesController::class, 'boletaPdf']);
        
        Route::get('/pagos', [GastosComunesController::class, 'pagos']);
        Route::post('/pagos', [GastosComunesController::class, 'registrarPago']);
        
        Route::get('/morosidad', [GastosComunesController::class, 'morosidad']);
        Route::get('/conceptos', [GastosComunesController::class, 'conceptos']);
    });

    // Arriendos
    Route::prefix('arriendos')->group(function () {
        Route::get('/contratos', [ArriendosController::class, 'contratos']);
        Route::post('/contratos', [ArriendosController::class, 'crearContrato']);
        Route::get('/contratos/{id}', [ArriendosController::class, 'showContrato']);
        Route::put('/contratos/{id}', [ArriendosController::class, 'updateContrato']);
        
        Route::get('/facturas', [ArriendosController::class, 'facturas']);
        Route::post('/facturas/generar', [ArriendosController::class, 'generarFacturas']);
        Route::get('/facturas/{id}', [ArriendosController::class, 'showFactura']);
        Route::get('/facturas/{id}/pdf', [ArriendosController::class, 'facturaPdf']);
        
        Route::get('/arrendatarios', [ArriendosController::class, 'arrendatarios']);
        Route::post('/arrendatarios', [ArriendosController::class, 'crearArrendatario']);
    });

    // Distribución
    Route::prefix('distribucion')->group(function () {
        Route::get('/', [DistribucionController::class, 'index']);
        Route::post('/', [DistribucionController::class, 'crear']);
        Route::get('/{id}', [DistribucionController::class, 'show']);
        Route::post('/{id}/procesar', [DistribucionController::class, 'procesar']);
        Route::post('/{id}/aprobar', [DistribucionController::class, 'aprobar']);
        Route::get('/{id}/detalle', [DistribucionController::class, 'detalle']);
        
        Route::get('/certificados', [DistribucionController::class, 'certificados']);
        Route::post('/certificados/generar-masivo', [DistribucionController::class, 'generarCertificadosMasivo']);
        Route::get('/certificados/{id}/pdf', [DistribucionController::class, 'certificadoPdf']);
    });

    // RRHH
    Route::prefix('rrhh')->group(function () {
        Route::get('/empleados', [RRHHController::class, 'index']);
        Route::post('/empleados', [RRHHController::class, 'store']);
        Route::get('/empleados/{id}', [RRHHController::class, 'show']);
        Route::put('/empleados/{id}', [RRHHController::class, 'update']);
        Route::delete('/empleados/{id}', [RRHHController::class, 'destroy']);
        
        Route::get('/liquidaciones', [RRHHController::class, 'liquidaciones']);
        Route::get('/liquidaciones/empleado/{id}', [RRHHController::class, 'liquidacionesEmpleado']);
        Route::post('/liquidaciones/generar', [RRHHController::class, 'generarLiquidacion']);
        Route::get('/liquidaciones/{id}', [RRHHController::class, 'showLiquidacion']);
        Route::get('/liquidaciones/{id}/pdf', [RRHHController::class, 'liquidacionPdf']);
        
        Route::get('/afp', [RRHHController::class, 'afp']);
        Route::get('/isapres', [RRHHController::class, 'isapres']);
        Route::get('/indicadores', [RRHHController::class, 'indicadores']);
    });

    // Contabilidad
    Route::prefix('contabilidad')->group(function () {
        Route::get('/plan-cuentas', [ContabilidadController::class, 'planCuentas']);
        Route::post('/plan-cuentas', [ContabilidadController::class, 'crearCuenta']);
        
        Route::get('/asientos', [ContabilidadController::class, 'asientos']);
        Route::post('/asientos', [ContabilidadController::class, 'crearAsiento']);
        Route::get('/asientos/{id}', [ContabilidadController::class, 'showAsiento']);
        
        Route::get('/libro-diario', [ContabilidadController::class, 'libroDiario']);
        Route::get('/libro-mayor', [ContabilidadController::class, 'libroMayor']);
        Route::get('/balance', [ContabilidadController::class, 'balance']);
        
        // Balance General (reportes tributarios)
        Route::get('/balance-general', [ReportesTributariosController::class, 'listarBalances']);
        Route::post('/balance-general/generar', [ReportesTributariosController::class, 'generarBalanceGeneral']);
        Route::get('/balance-general/{id}/pdf', [ReportesTributariosController::class, 'descargarBalancePdf']);
        
        // Estado de Resultados
        Route::get('/estado-resultados', [ReportesTributariosController::class, 'listarEstadosResultados']);
        Route::post('/estado-resultados/generar', [ReportesTributariosController::class, 'generarEstadoResultados']);
        Route::get('/estado-resultados/{id}/pdf', [ReportesTributariosController::class, 'descargarEstadoResultadosPdf']);
    });

    // Reuniones
    Route::prefix('reuniones')->group(function () {
        Route::get('/', [ReunionesController::class, 'index']);
        Route::post('/', [ReunionesController::class, 'store']);
        Route::get('/{id}', [ReunionesController::class, 'show']);
        Route::put('/{id}', [ReunionesController::class, 'update']);
        Route::delete('/{id}', [ReunionesController::class, 'destroy']);
        
        Route::post('/{id}/convocar', [ReunionesController::class, 'convocar']);
        Route::post('/{id}/iniciar', [ReunionesController::class, 'iniciar']);
        Route::post('/{id}/finalizar', [ReunionesController::class, 'finalizar']);
        
        Route::get('/{id}/convocados', [ReunionesController::class, 'convocados']);
        Route::post('/{id}/convocados', [ReunionesController::class, 'agregarConvocados']);
        
        Route::get('/{id}/votaciones', [ReunionesController::class, 'votaciones']);
        Route::post('/{id}/votaciones', [ReunionesController::class, 'crearVotacion']);
        Route::post('/{id}/votaciones/{votacionId}/iniciar', [ReunionesController::class, 'iniciarVotacion']);
        Route::post('/{id}/votaciones/{votacionId}/votar', [ReunionesController::class, 'votar']);
        Route::post('/{id}/votaciones/{votacionId}/cerrar', [ReunionesController::class, 'cerrarVotacion']);
        
        Route::get('/{id}/acta', [ReunionesController::class, 'acta']);
        Route::post('/{id}/acta/generar', [ReunionesController::class, 'generarActa']);
    });

    // Asistente Legal
    Route::prefix('legal')->group(function () {
        Route::get('/consultas', [AsistenteLegalController::class, 'index']);
        Route::post('/consultas', [AsistenteLegalController::class, 'consultar']);
        Route::get('/categorias', [AsistenteLegalController::class, 'categorias']);
        Route::get('/faq', [AsistenteLegalController::class, 'faq']);
        
        Route::get('/oficios', [AsistenteLegalController::class, 'oficios']);
        Route::post('/oficios', [AsistenteLegalController::class, 'crearOficio']);
        Route::get('/oficios/{id}', [AsistenteLegalController::class, 'showOficio']);
        Route::get('/oficios/{id}/pdf', [AsistenteLegalController::class, 'oficioPdf']);
        
        Route::get('/plantillas', [AsistenteLegalController::class, 'plantillas']);
        Route::get('/instituciones', [AsistenteLegalController::class, 'instituciones']);
        
        Route::get('/certificados', [AsistenteLegalController::class, 'certificados']);
        Route::post('/certificados/generar', [AsistenteLegalController::class, 'generarCertificado']);
    });

    // Protección de Datos
    Route::prefix('proteccion-datos')->group(function () {
        Route::get('/dashboard', [ProteccionDatosController::class, 'dashboardCumplimiento']);
        Route::get('/solicitudes', [ProteccionDatosController::class, 'listarSolicitudes']);
        Route::put('/solicitudes/{id}', [ProteccionDatosController::class, 'procesarSolicitud']);
        
        Route::post('/consentimientos', [ProteccionDatosController::class, 'registrarConsentimiento']);
        Route::post('/consentimientos/revocar', [ProteccionDatosController::class, 'revocarConsentimiento']);
        Route::get('/consentimientos/persona/{personaId}', [ProteccionDatosController::class, 'obtenerConsentimientos']);
        
        Route::get('/tratamientos', [ProteccionDatosController::class, 'listarTratamientos']);
        Route::post('/tratamientos', [ProteccionDatosController::class, 'crearTratamiento']);
        
        Route::get('/brechas', [ProteccionDatosController::class, 'listarBrechas']);
        Route::post('/brechas', [ProteccionDatosController::class, 'reportarBrecha']);
        
        Route::post('/politicas', [ProteccionDatosController::class, 'crearPolitica']);
        Route::post('/anonimizar', [ProteccionDatosController::class, 'anonimizarDatos']);
        Route::get('/logs/persona/{personaId}', [ProteccionDatosController::class, 'logsAccesoPersona']);
    });

    // Reportes Tributarios
    Route::prefix('tributario')->group(function () {
        // Declaraciones Juradas
        Route::get('/declaraciones-juradas', [ReportesTributariosController::class, 'listarDeclaraciones']);
        Route::post('/declaraciones-juradas/dj1887', [ReportesTributariosController::class, 'generarDJ1887']);
        Route::get('/declaraciones-juradas/{id}/csv', [ReportesTributariosController::class, 'descargarDJ1887Csv']);
        
        // Reportes consolidados
        Route::post('/reportes/consolidado', [ReportesTributariosController::class, 'generarReporteConsolidadoDistribucion']);
        Route::get('/reportes/contribuyente/{personaId}', [ReportesTributariosController::class, 'obtenerDetalleContribuyente']);
        Route::get('/reportes/certificado-individual/{detalleId}/pdf', [ReportesTributariosController::class, 'descargarCertificadoIndividual']);
        Route::get('/reportes/certificado-consolidado/{personaId}/pdf', [ReportesTributariosController::class, 'descargarCertificadoConsolidado']);
        
        // Certificados de deuda
        Route::get('/certificados-deuda', [ReportesTributariosController::class, 'listarCertificadosDeuda']);
        Route::post('/certificados-deuda/generar', [ReportesTributariosController::class, 'generarCertificadoDeuda']);
        Route::get('/certificados-deuda/{id}/pdf', [ReportesTributariosController::class, 'descargarCertificadoDeudaPdf']);
        
        // Checklist cumplimiento
        Route::post('/cumplimiento/checklist', [ReportesTributariosController::class, 'generarChecklistCumplimiento']);
        Route::get('/cumplimiento/checklist/edificio/{edificioId}', [ReportesTributariosController::class, 'checklistEdificio']);
    });
});

// ========================================
// RUTAS PÚBLICAS
// ========================================

// Ejercicio de derechos ARCO+ (sin autenticación)
Route::prefix('privacidad')->group(function () {
    Route::post('/derecho-acceso', [ProteccionDatosController::class, 'ejercerDerechoAcceso']);
    Route::post('/derecho-rectificacion', [ProteccionDatosController::class, 'ejercerDerechoRectificacion']);
    Route::post('/derecho-cancelacion', [ProteccionDatosController::class, 'ejercerDerechoCancelacion']);
    Route::post('/derecho-oposicion', [ProteccionDatosController::class, 'ejercerDerechoOposicion']);
    Route::post('/derecho-portabilidad', [ProteccionDatosController::class, 'ejercerDerechoPortabilidad']);
    Route::get('/solicitud/{numero}', [ProteccionDatosController::class, 'consultarEstadoSolicitud']);
    Route::get('/politica', [ProteccionDatosController::class, 'obtenerPoliticaVigente']);
});

// Verificación pública de certificados
Route::get('/verificar/{codigo}', [ReportesTributariosController::class, 'verificarCertificado']);
Route::get('/verificar-certificado/{codigo}', [AsistenteLegalController::class, 'verificarCertificado']);

// Sala de reunión pública
Route::get('/sala/{uuid}', [ReunionesController::class, 'accederSala']);
