<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

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
