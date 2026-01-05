<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // ARRENDATARIOS (EMPRESAS TELECOM, ETC)
        // ========================================
        Schema::create('arrendatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('rut', 12);
            $table->string('razon_social', 200);
            $table->string('nombre_fantasia', 200)->nullable();
            $table->string('giro', 200)->nullable();
            $table->string('direccion', 300);
            $table->string('comuna', 100)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 255);
            $table->string('contacto_nombre', 200)->nullable();
            $table->string('contacto_telefono', 20)->nullable();
            $table->string('contacto_email', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'rut']);
        });

        // ========================================
        // CONTRATOS DE ARRIENDO
        // ========================================
        Schema::create('contratos_arriendo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arrendatario_id')->constrained()->cascadeOnDelete();
            
            $table->string('numero_contrato', 50)->nullable();
            $table->enum('tipo_espacio', ['azotea', 'fachada', 'subterraneo', 'terreno', 'sala_tecnica', 'otro'])->default('azotea');
            $table->string('ubicacion_espacio', 200);
            $table->decimal('superficie_m2', 10, 2)->nullable();
            $table->text('descripcion_espacio')->nullable();
            
            $table->date('fecha_inicio');
            $table->date('fecha_termino');
            $table->integer('duracion_meses')->nullable();
            $table->boolean('renovacion_automatica')->default(false);
            $table->integer('preaviso_dias')->default(60);
            
            $table->decimal('monto_mensual', 15, 2);
            $table->enum('moneda', ['CLP', 'UF', 'USD'])->default('UF');
            $table->integer('dia_facturacion')->default(1);
            $table->integer('dias_pago')->default(30);
            
            $table->enum('reajuste_tipo', ['ipc', 'uf', 'fijo', 'ninguno'])->default('uf');
            $table->decimal('reajuste_porcentaje', 5, 2)->nullable();
            $table->integer('reajuste_cada_meses')->default(12);
            $table->date('ultimo_reajuste')->nullable();
            
            $table->decimal('garantia_monto', 15, 2)->nullable();
            $table->string('garantia_tipo', 50)->nullable();
            $table->date('garantia_vencimiento')->nullable();
            
            $table->enum('estado', ['borrador', 'activo', 'suspendido', 'terminado', 'renovado'])->default('activo');
            $table->date('fecha_termino_real')->nullable();
            $table->string('motivo_termino', 200)->nullable();
            
            $table->string('documento_contrato')->nullable();
            $table->text('clausulas_especiales')->nullable();
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'estado']);
            $table->index(['edificio_id', 'estado']);
        });

        // ========================================
        // FACTURAS DE ARRIENDO
        // ========================================
        Schema::create('facturas_arriendo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contrato_id')->constrained('contratos_arriendo')->cascadeOnDelete();
            
            $table->string('numero_factura', 30)->nullable();
            $table->string('folio_sii', 30)->nullable();
            $table->integer('periodo_mes');
            $table->integer('periodo_anio');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            
            $table->decimal('monto_neto', 15, 2);
            $table->decimal('iva', 15, 2);
            $table->decimal('monto_total', 15, 2);
            $table->decimal('monto_uf', 15, 4)->nullable();
            $table->decimal('valor_uf_usado', 15, 2)->nullable();
            
            $table->enum('estado', ['borrador', 'emitida', 'enviada_sii', 'aceptada', 'rechazada', 'pagada', 'anulada'])->default('borrador');
            
            $table->date('fecha_pago')->nullable();
            $table->decimal('monto_pagado', 15, 2)->nullable();
            $table->string('medio_pago', 50)->nullable();
            $table->string('referencia_pago', 100)->nullable();
            
            $table->string('archivo_pdf')->nullable();
            $table->string('archivo_xml')->nullable();
            $table->text('respuesta_sii')->nullable();
            $table->timestamp('enviada_sii_at')->nullable();
            
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contrato_id', 'periodo_mes', 'periodo_anio']);
            $table->index(['tenant_id', 'estado']);
        });

        // ========================================
        // DISTRIBUCIÓN DE INGRESOS
        // ========================================
        Schema::create('distribuciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos_arriendo')->nullOnDelete();
            $table->foreignId('factura_id')->nullable()->constrained('facturas_arriendo')->nullOnDelete();
            
            $table->integer('periodo_mes');
            $table->integer('periodo_anio');
            $table->string('concepto', 200);
            
            $table->decimal('monto_bruto', 15, 2);
            $table->decimal('gastos_administracion', 15, 2)->default(0);
            $table->decimal('porcentaje_administracion', 5, 2)->default(0);
            $table->decimal('otros_descuentos', 15, 2)->default(0);
            $table->decimal('monto_neto', 15, 2);
            
            $table->enum('metodo_distribucion', ['prorrateo', 'igualitario', 'personalizado'])->default('prorrateo');
            $table->enum('estado', ['borrador', 'procesada', 'aprobada', 'distribuida', 'anulada'])->default('borrador');
            
            $table->integer('total_beneficiarios')->default(0);
            
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobada_at')->nullable();
            $table->foreignId('procesada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('procesada_at')->nullable();
            
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'periodo_anio', 'periodo_mes']);
            $table->index(['edificio_id', 'estado']);
        });

        // ========================================
        // DETALLE DE DISTRIBUCIÓN (POR UNIDAD)
        // ========================================
        Schema::create('distribucion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribucion_id')->constrained('distribuciones')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('beneficiario_id')->constrained('personas');
            
            $table->decimal('porcentaje_participacion', 10, 6);
            $table->decimal('monto_bruto', 15, 2);
            $table->decimal('retencion_impuesto', 15, 2)->default(0);
            $table->decimal('monto_neto', 15, 2);
            
            $table->enum('forma_pago', ['transferencia', 'cheque', 'descuento_gc', 'otro'])->default('descuento_gc');
            $table->boolean('pagado')->default(false);
            $table->date('fecha_pago')->nullable();
            $table->string('referencia_pago', 100)->nullable();
            
            $table->boolean('certificado_generado')->default(false);
            $table->string('certificado_url')->nullable();
            $table->timestamp('certificado_at')->nullable();
            
            $table->timestamps();

            $table->unique(['distribucion_id', 'unidad_id']);
            $table->index('beneficiario_id');
        });

        // ========================================
        // CERTIFICADOS DE RENTA
        // ========================================
        Schema::create('certificados_renta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('beneficiario_id')->constrained('personas');
            
            $table->integer('anio');
            $table->string('numero_certificado', 30);
            $table->date('fecha_emision');
            
            $table->decimal('renta_total', 15, 2);
            $table->decimal('renta_articulo_17', 15, 2)->default(0);
            $table->decimal('renta_articulo_20', 15, 2)->default(0);
            $table->decimal('retenciones', 15, 2)->default(0);
            
            $table->enum('tipo_certificado', ['art_17', 'art_20', 'mixto'])->default('art_17');
            $table->string('archivo_pdf')->nullable();
            $table->string('codigo_verificacion', 20);
            
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->unique(['edificio_id', 'unidad_id', 'anio']);
            $table->index('codigo_verificacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_renta');
        Schema::dropIfExists('distribucion_detalle');
        Schema::dropIfExists('distribuciones');
        Schema::dropIfExists('facturas_arriendo');
        Schema::dropIfExists('contratos_arriendo');
        Schema::dropIfExists('arrendatarios');
    }
};
