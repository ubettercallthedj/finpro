<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DATAPOLIS PRO - Migración Reportes Tributarios y Certificados
     * 
     * Tablas para:
     * - Balances Generales (formato SII/F22)
     * - Estados de Resultados
     * - Declaraciones Juradas (DJ 1887, etc.)
     * - Reportes consolidados de distribución
     * - Certificados de no deuda / pago GGCC
     * - Checklist cumplimiento legal por unidad
     */
    public function up(): void
    {
        // =====================================================
        // BALANCES GENERALES (Formato SII)
        // =====================================================
        Schema::create('balances_generales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            
            $table->year('anio_tributario');
            $table->enum('tipo', ['anual', 'mensual', 'trimestral', 'semestral'])->default('anual');
            $table->tinyInteger('mes')->nullable(); // Si es mensual
            $table->date('fecha_inicio');
            $table->date('fecha_cierre');
            
            // ACTIVOS
            $table->decimal('activo_circulante_caja', 15, 2)->default(0);
            $table->decimal('activo_circulante_bancos', 15, 2)->default(0);
            $table->decimal('activo_circulante_cuentas_cobrar', 15, 2)->default(0);
            $table->decimal('activo_circulante_documentos_cobrar', 15, 2)->default(0);
            $table->decimal('activo_circulante_deudores_gc', 15, 2)->default(0);
            $table->decimal('activo_circulante_arriendos_cobrar', 15, 2)->default(0);
            $table->decimal('activo_circulante_iva_credito', 15, 2)->default(0);
            $table->decimal('activo_circulante_otros', 15, 2)->default(0);
            $table->decimal('total_activo_circulante', 15, 2)->default(0);
            
            $table->decimal('activo_fijo_terrenos', 15, 2)->default(0);
            $table->decimal('activo_fijo_construcciones', 15, 2)->default(0);
            $table->decimal('activo_fijo_muebles', 15, 2)->default(0);
            $table->decimal('activo_fijo_equipos', 15, 2)->default(0);
            $table->decimal('activo_fijo_vehiculos', 15, 2)->default(0);
            $table->decimal('activo_fijo_depreciacion_acum', 15, 2)->default(0);
            $table->decimal('total_activo_fijo', 15, 2)->default(0);
            
            $table->decimal('otros_activos', 15, 2)->default(0);
            $table->decimal('total_activos', 15, 2)->default(0);
            
            // PASIVOS
            $table->decimal('pasivo_circulante_proveedores', 15, 2)->default(0);
            $table->decimal('pasivo_circulante_remuneraciones', 15, 2)->default(0);
            $table->decimal('pasivo_circulante_cotizaciones', 15, 2)->default(0);
            $table->decimal('pasivo_circulante_impuestos', 15, 2)->default(0);
            $table->decimal('pasivo_circulante_iva_debito', 15, 2)->default(0);
            $table->decimal('pasivo_circulante_arriendos_anticipados', 15, 2)->default(0);
            $table->decimal('pasivo_circulante_otros', 15, 2)->default(0);
            $table->decimal('total_pasivo_circulante', 15, 2)->default(0);
            
            $table->decimal('pasivo_largo_plazo_deudas', 15, 2)->default(0);
            $table->decimal('pasivo_largo_plazo_provisiones', 15, 2)->default(0);
            $table->decimal('total_pasivo_largo_plazo', 15, 2)->default(0);
            
            $table->decimal('total_pasivos', 15, 2)->default(0);
            
            // PATRIMONIO
            $table->decimal('patrimonio_fondo_comun', 15, 2)->default(0);
            $table->decimal('patrimonio_fondo_reserva', 15, 2)->default(0);
            $table->decimal('patrimonio_resultados_acumulados', 15, 2)->default(0);
            $table->decimal('patrimonio_resultado_ejercicio', 15, 2)->default(0);
            $table->decimal('total_patrimonio', 15, 2)->default(0);
            
            $table->decimal('total_pasivo_patrimonio', 15, 2)->default(0);
            
            // Validación contable
            $table->boolean('cuadrado')->default(false); // Activo = Pasivo + Patrimonio
            $table->decimal('diferencia', 15, 2)->default(0);
            
            $table->enum('estado', ['borrador', 'generado', 'aprobado', 'presentado'])->default('borrador');
            $table->foreignId('generado_por')->nullable()->constrained('users');
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestamp('fecha_aprobacion')->nullable();
            
            $table->text('notas')->nullable();
            $table->timestamps();
            
            $table->unique(['tenant_id', 'edificio_id', 'anio_tributario', 'tipo', 'mes'], 'balance_periodo_unique');
        });

        // =====================================================
        // ESTADOS DE RESULTADOS
        // =====================================================
        Schema::create('estados_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            
            $table->year('anio_tributario');
            $table->enum('tipo', ['anual', 'mensual', 'trimestral'])->default('anual');
            $table->tinyInteger('mes')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_cierre');
            
            // INGRESOS OPERACIONALES
            $table->decimal('ingresos_gastos_comunes', 15, 2)->default(0);
            $table->decimal('ingresos_fondo_reserva', 15, 2)->default(0);
            $table->decimal('ingresos_multas_intereses', 15, 2)->default(0);
            $table->decimal('ingresos_arriendos_antenas', 15, 2)->default(0);
            $table->decimal('ingresos_arriendos_publicidad', 15, 2)->default(0);
            $table->decimal('ingresos_arriendos_otros', 15, 2)->default(0);
            $table->decimal('ingresos_estacionamientos', 15, 2)->default(0);
            $table->decimal('ingresos_sala_eventos', 15, 2)->default(0);
            $table->decimal('otros_ingresos_operacionales', 15, 2)->default(0);
            $table->decimal('total_ingresos_operacionales', 15, 2)->default(0);
            
            // COSTOS Y GASTOS OPERACIONALES
            $table->decimal('gastos_remuneraciones', 15, 2)->default(0);
            $table->decimal('gastos_cotizaciones_previsionales', 15, 2)->default(0);
            $table->decimal('gastos_servicios_basicos', 15, 2)->default(0);
            $table->decimal('gastos_mantenciones', 15, 2)->default(0);
            $table->decimal('gastos_reparaciones', 15, 2)->default(0);
            $table->decimal('gastos_seguros', 15, 2)->default(0);
            $table->decimal('gastos_aseo', 15, 2)->default(0);
            $table->decimal('gastos_vigilancia', 15, 2)->default(0);
            $table->decimal('gastos_administracion', 15, 2)->default(0);
            $table->decimal('gastos_legales', 15, 2)->default(0);
            $table->decimal('gastos_contables', 15, 2)->default(0);
            $table->decimal('gastos_depreciacion', 15, 2)->default(0);
            $table->decimal('otros_gastos_operacionales', 15, 2)->default(0);
            $table->decimal('total_gastos_operacionales', 15, 2)->default(0);
            
            // RESULTADO OPERACIONAL
            $table->decimal('resultado_operacional', 15, 2)->default(0);
            
            // INGRESOS/GASTOS NO OPERACIONALES
            $table->decimal('ingresos_financieros', 15, 2)->default(0);
            $table->decimal('gastos_financieros', 15, 2)->default(0);
            $table->decimal('otros_ingresos', 15, 2)->default(0);
            $table->decimal('otros_gastos', 15, 2)->default(0);
            $table->decimal('resultado_no_operacional', 15, 2)->default(0);
            
            // RESULTADO ANTES DE DISTRIBUCIÓN
            $table->decimal('resultado_antes_distribucion', 15, 2)->default(0);
            
            // DISTRIBUCIÓN A COPROPIETARIOS (Ley 21.713)
            $table->decimal('distribucion_copropietarios', 15, 2)->default(0);
            $table->decimal('monto_art_17_n3', 15, 2)->default(0); // No constituye renta
            
            // RESULTADO DEL EJERCICIO
            $table->decimal('resultado_ejercicio', 15, 2)->default(0);
            
            $table->enum('estado', ['borrador', 'generado', 'aprobado', 'presentado'])->default('borrador');
            $table->foreignId('generado_por')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->unique(['tenant_id', 'edificio_id', 'anio_tributario', 'tipo', 'mes'], 'eerr_periodo_unique');
        });

        // =====================================================
        // DECLARACIONES JURADAS
        // =====================================================
        Schema::create('declaraciones_juradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            
            $table->string('tipo_dj', 10); // DJ1887, DJ1947, etc.
            $table->year('anio_tributario');
            $table->string('numero_declaracion', 30)->nullable();
            
            $table->date('fecha_generacion');
            $table->date('fecha_presentacion')->nullable();
            $table->date('fecha_vencimiento');
            
            $table->integer('cantidad_informados')->default(0);
            $table->decimal('monto_total_informado', 15, 2)->default(0);
            
            $table->json('detalle')->nullable(); // Detalle completo de la DJ
            $table->json('resumen')->nullable();
            
            $table->enum('estado', ['borrador', 'generada', 'presentada', 'rectificada', 'rechazada'])->default('borrador');
            $table->string('folio_sii', 30)->nullable();
            $table->text('observaciones_sii')->nullable();
            
            $table->foreignId('generado_por')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->unique(['tenant_id', 'edificio_id', 'tipo_dj', 'anio_tributario'], 'dj_unica');
        });

        // =====================================================
        // REPORTE CONSOLIDADO DE ARRIENDOS Y DISTRIBUCIÓN
        // =====================================================
        Schema::create('reportes_distribucion_consolidado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            
            $table->year('anio');
            $table->string('numero_reporte', 30)->unique();
            
            // Totales de ingresos por tipo
            $table->decimal('total_arriendos_antenas', 15, 2)->default(0);
            $table->decimal('total_arriendos_publicidad', 15, 2)->default(0);
            $table->decimal('total_arriendos_espacios', 15, 2)->default(0);
            $table->decimal('total_arriendos_estacionamientos', 15, 2)->default(0);
            $table->decimal('total_otros_ingresos', 15, 2)->default(0);
            $table->decimal('total_ingresos_brutos', 15, 2)->default(0);
            
            // Gastos asociados (si se deducen antes de distribuir)
            $table->decimal('gastos_asociados', 15, 2)->default(0);
            $table->decimal('total_neto_distribuible', 15, 2)->default(0);
            
            // Distribución
            $table->decimal('total_distribuido', 15, 2)->default(0);
            $table->decimal('excedente_no_distribuido', 15, 2)->default(0);
            
            // Conteo
            $table->integer('cantidad_unidades_beneficiarias')->default(0);
            $table->integer('cantidad_copropietarios_beneficiarios')->default(0);
            $table->integer('cantidad_distribuciones')->default(0);
            
            $table->json('detalle_por_tipo_ingreso')->nullable();
            $table->json('detalle_por_arrendatario')->nullable();
            $table->json('resumen_mensual')->nullable();
            
            $table->enum('estado', ['borrador', 'generado', 'aprobado'])->default('borrador');
            $table->timestamps();
        });

        // =====================================================
        // DETALLE DISTRIBUCIÓN POR CONTRIBUYENTE
        // =====================================================
        Schema::create('distribucion_detalle_contribuyente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporte_consolidado_id')->constrained('reportes_distribucion_consolidado')->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete(); // Copropietario/Contribuyente
            
            $table->year('anio');
            
            // Identificación contribuyente
            $table->string('rut_contribuyente', 12);
            $table->string('nombre_contribuyente', 200);
            $table->string('direccion_contribuyente', 255)->nullable();
            $table->string('email_contribuyente', 255)->nullable();
            
            // Identificación unidad
            $table->string('numero_unidad', 20);
            $table->string('tipo_unidad', 50);
            $table->string('rol_avaluo', 20)->nullable();
            $table->decimal('prorrateo', 8, 6);
            
            // Montos anuales
            $table->decimal('total_ingresos_brutos', 15, 2)->default(0);
            $table->decimal('total_distribuido', 15, 2)->default(0);
            $table->decimal('monto_art_17_n3', 15, 2)->default(0); // No constituye renta
            $table->decimal('monto_afecto_impuesto', 15, 2)->default(0);
            $table->decimal('retenciones', 15, 2)->default(0);
            
            // Detalle mensual
            $table->json('detalle_mensual')->nullable();
            /*
            [
                {"mes": 1, "monto_bruto": 100000, "distribuido": 100000, "fecha_pago": "2025-02-15", "medio_pago": "transferencia", "banco": "BCI", "comprobante": "123456"},
                ...
            ]
            */
            
            // Información de pagos
            $table->integer('cantidad_pagos')->default(0);
            $table->date('primer_pago')->nullable();
            $table->date('ultimo_pago')->nullable();
            $table->string('banco_pago', 100)->nullable();
            $table->string('cuenta_pago', 30)->nullable();
            
            // Certificado
            $table->string('numero_certificado', 30)->nullable();
            $table->date('fecha_certificado')->nullable();
            $table->string('codigo_verificacion', 20)->nullable();
            
            $table->timestamps();
            
            $table->index(['persona_id', 'anio']);
            $table->index(['rut_contribuyente', 'anio']);
        });

        // =====================================================
        // VISTA CONSOLIDADA MULTI-PROPIEDAD POR CONTRIBUYENTE
        // =====================================================
        Schema::create('distribucion_consolidado_contribuyente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
            
            $table->year('anio');
            $table->string('rut_contribuyente', 12);
            $table->string('nombre_contribuyente', 200);
            
            // Totales consolidados de TODAS las propiedades
            $table->integer('cantidad_propiedades')->default(0);
            $table->decimal('total_ingresos_todas_propiedades', 15, 2)->default(0);
            $table->decimal('total_distribuido_todas_propiedades', 15, 2)->default(0);
            $table->decimal('total_art_17_n3', 15, 2)->default(0);
            $table->decimal('total_afecto_impuesto', 15, 2)->default(0);
            $table->decimal('total_retenciones', 15, 2)->default(0);
            
            // Detalle por propiedad
            $table->json('detalle_por_propiedad')->nullable();
            /*
            [
                {"edificio_id": 1, "edificio_nombre": "Torre A", "unidad_id": 5, "unidad_numero": "101", "monto_distribuido": 500000},
                {"edificio_id": 2, "edificio_nombre": "Torre B", "unidad_id": 15, "unidad_numero": "201", "monto_distribuido": 300000},
            ]
            */
            
            // Certificado consolidado
            $table->string('numero_certificado_consolidado', 30)->nullable();
            $table->date('fecha_certificado')->nullable();
            $table->string('codigo_verificacion', 20)->nullable();
            
            $table->timestamps();
            
            $table->unique(['persona_id', 'anio']);
        });

        // =====================================================
        // CERTIFICADOS DE NO DEUDA / PAGO GGCC
        // =====================================================
        Schema::create('certificados_deuda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('solicitante_id')->nullable()->constrained('personas');
            
            $table->string('numero_certificado', 30)->unique();
            $table->enum('tipo', [
                'no_deuda',           // Certificado de no deuda
                'pago_al_dia',        // Certificado de pago al día
                'estado_cuenta',      // Estado de cuenta detallado
                'deuda_pendiente',    // Certificado con deuda
                'finiquito'           // Finiquito de deudas
            ]);
            
            $table->date('fecha_emision');
            $table->date('fecha_validez'); // Válido hasta
            $table->date('fecha_corte'); // Fecha hasta la cual se calculó
            
            // Estado de la unidad a la fecha de corte
            $table->boolean('tiene_deuda')->default(false);
            $table->decimal('deuda_gastos_comunes', 12, 2)->default(0);
            $table->decimal('deuda_fondo_reserva', 12, 2)->default(0);
            $table->decimal('deuda_multas', 12, 2)->default(0);
            $table->decimal('deuda_intereses', 12, 2)->default(0);
            $table->decimal('deuda_otros', 12, 2)->default(0);
            $table->decimal('deuda_total', 12, 2)->default(0);
            
            // Último pago registrado
            $table->date('fecha_ultimo_pago')->nullable();
            $table->decimal('monto_ultimo_pago', 12, 2)->nullable();
            
            // Detalle de períodos
            $table->json('detalle_periodos')->nullable();
            /*
            [
                {"periodo": "2025-01", "estado": "pagado", "monto": 150000, "fecha_pago": "2025-01-15"},
                {"periodo": "2025-02", "estado": "pagado", "monto": 150000, "fecha_pago": "2025-02-10"},
                ...
            ]
            */
            
            // Verificación
            $table->string('codigo_verificacion', 20)->unique();
            $table->string('qr_code', 500)->nullable();
            
            // Motivo de solicitud
            $table->string('motivo_solicitud', 100)->nullable(); // venta, arriendo, banco, otro
            $table->text('observaciones')->nullable();
            
            $table->foreignId('emitido_por')->constrained('users');
            $table->timestamps();
            
            $table->index(['unidad_id', 'fecha_emision']);
            $table->index(['codigo_verificacion']);
        });

        // =====================================================
        // CHECKLIST CUMPLIMIENTO LEGAL POR UNIDAD
        // =====================================================
        Schema::create('checklist_cumplimiento_unidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            
            $table->year('anio');
            $table->date('fecha_revision');
            
            // CUMPLIMIENTO GASTOS COMUNES
            $table->boolean('gc_pagos_al_dia')->default(false);
            $table->boolean('gc_sin_deuda_historica')->default(false);
            $table->boolean('gc_fondo_reserva_pagado')->default(false);
            $table->text('gc_observaciones')->nullable();
            
            // CUMPLIMIENTO DISTRIBUCIÓN
            $table->boolean('dist_recibio_distribucion')->default(false);
            $table->boolean('dist_certificado_emitido')->default(false);
            $table->boolean('dist_datos_bancarios_actualizados')->default(false);
            $table->text('dist_observaciones')->nullable();
            
            // CUMPLIMIENTO LEGAL GENERAL
            $table->boolean('legal_datos_propietario_actualizados')->default(false);
            $table->boolean('legal_email_verificado')->default(false);
            $table->boolean('legal_acepto_reglamento')->default(false);
            $table->boolean('legal_acepto_politica_datos')->default(false);
            $table->date('legal_fecha_aceptacion_reglamento')->nullable();
            $table->text('legal_observaciones')->nullable();
            
            // CUMPLIMIENTO LEY 21.442
            $table->boolean('ley21442_inscrito_registro')->default(false);
            $table->boolean('ley21442_prorrateo_actualizado')->default(false);
            $table->boolean('ley21442_participa_asambleas')->default(false);
            $table->text('ley21442_observaciones')->nullable();
            
            // CUMPLIMIENTO PROTECCIÓN DATOS
            $table->boolean('datos_consentimiento_vigente')->default(false);
            $table->boolean('datos_solicitudes_arco_atendidas')->default(true);
            $table->text('datos_observaciones')->nullable();
            
            // RESUMEN
            $table->integer('total_items_evaluados')->default(0);
            $table->integer('items_cumplidos')->default(0);
            $table->decimal('porcentaje_cumplimiento', 5, 2)->default(0);
            $table->enum('estado_general', ['cumple', 'cumple_parcial', 'no_cumple', 'pendiente'])->default('pendiente');
            
            $table->json('alertas')->nullable();
            $table->text('recomendaciones')->nullable();
            
            $table->foreignId('revisado_por')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->unique(['unidad_id', 'anio']);
        });

        // =====================================================
        // HISTORIAL DE CERTIFICADOS TRIBUTARIOS EMITIDOS
        // =====================================================
        Schema::create('historial_certificados_tributarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades');
            $table->foreignId('persona_id')->constrained();
            
            $table->enum('tipo_certificado', [
                'renta_individual',        // Cert. renta por unidad
                'renta_consolidado',       // Cert. renta multi-propiedad
                'no_deuda',                // Cert. no deuda
                'pago_al_dia',             // Cert. pago al día
                'cumplimiento_legal',      // Cert. cumplimiento
                'retencion',               // Cert. retención (si aplica)
                'participacion'            // Cert. participación en comunidad
            ]);
            
            $table->year('anio_tributario')->nullable();
            $table->string('numero_certificado', 30);
            $table->string('codigo_verificacion', 20);
            
            $table->date('fecha_emision');
            $table->date('fecha_validez')->nullable();
            
            $table->decimal('monto_principal', 15, 2)->nullable();
            $table->json('datos_certificado')->nullable();
            
            $table->integer('descargas')->default(0);
            $table->timestamp('ultima_descarga')->nullable();
            
            $table->foreignId('emitido_por')->constrained('users');
            $table->timestamps();
            
            $table->index(['persona_id', 'tipo_certificado', 'anio_tributario']);
            $table->index(['codigo_verificacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_certificados_tributarios');
        Schema::dropIfExists('checklist_cumplimiento_unidad');
        Schema::dropIfExists('certificados_deuda');
        Schema::dropIfExists('distribucion_consolidado_contribuyente');
        Schema::dropIfExists('distribucion_detalle_contribuyente');
        Schema::dropIfExists('reportes_distribucion_consolidado');
        Schema::dropIfExists('declaraciones_juradas');
        Schema::dropIfExists('estados_resultados');
        Schema::dropIfExists('balances_generales');
    }
};
