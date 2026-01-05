<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // CONCEPTOS DE GASTOS COMUNES
        // ========================================
        Schema::create('conceptos_gc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['ordinario', 'extraordinario', 'fondo_reserva', 'multa', 'interes', 'otro'])->default('ordinario');
            $table->enum('metodo_calculo', ['fijo', 'prorrateo', 'consumo', 'manual'])->default('prorrateo');
            $table->boolean('aplica_iva')->default(false);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'codigo']);
        });

        // ========================================
        // PERÍODOS DE GASTOS COMUNES
        // ========================================
        Schema::create('periodos_gc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->integer('mes');
            $table->integer('anio');
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento');
            $table->date('fecha_segundo_vencimiento')->nullable();
            $table->enum('estado', ['abierto', 'cerrado', 'anulado'])->default('abierto');
            $table->decimal('total_emitido', 15, 2)->default(0);
            $table->decimal('total_recaudado', 15, 2)->default(0);
            $table->decimal('total_pendiente', 15, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['edificio_id', 'mes', 'anio']);
            $table->index(['tenant_id', 'estado']);
        });

        // ========================================
        // PRESUPUESTO GASTOS COMUNES
        // ========================================
        Schema::create('presupuestos_gc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->integer('anio');
            $table->foreignId('concepto_id')->constrained('conceptos_gc')->cascadeOnDelete();
            $table->decimal('monto_mensual', 15, 2)->default(0);
            $table->decimal('monto_anual', 15, 2)->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['edificio_id', 'anio', 'concepto_id']);
        });

        // ========================================
        // BOLETAS DE GASTOS COMUNES
        // ========================================
        Schema::create('boletas_gc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('periodo_id')->constrained('periodos_gc')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            
            $table->string('numero_boleta', 30);
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            $table->date('fecha_segundo_vencimiento')->nullable();
            
            $table->decimal('saldo_anterior', 15, 2)->default(0);
            $table->decimal('total_cargos', 15, 2)->default(0);
            $table->decimal('total_abonos', 15, 2)->default(0);
            $table->decimal('total_intereses', 15, 2)->default(0);
            $table->decimal('total_a_pagar', 15, 2)->default(0);
            
            $table->enum('estado', ['pendiente', 'pagada', 'parcial', 'vencida', 'anulada'])->default('pendiente');
            $table->integer('dias_mora')->default(0);
            
            $table->text('observaciones')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->timestamp('enviada_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['edificio_id', 'periodo_id', 'unidad_id']);
            $table->index(['tenant_id', 'estado']);
            $table->index(['unidad_id', 'estado']);
        });

        // ========================================
        // CARGOS (DETALLE DE BOLETA)
        // ========================================
        Schema::create('cargos_gc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boleta_id')->constrained('boletas_gc')->cascadeOnDelete();
            $table->foreignId('concepto_id')->constrained('conceptos_gc');
            $table->string('descripcion', 200)->nullable();
            $table->decimal('monto', 15, 2);
            $table->enum('tipo', ['ordinario', 'extraordinario', 'fondo_reserva', 'multa', 'interes', 'ajuste'])->default('ordinario');
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio_unitario', 15, 2)->nullable();
            $table->timestamps();

            $table->index('boleta_id');
        });

        // ========================================
        // PAGOS
        // ========================================
        Schema::create('pagos_gc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boleta_id')->constrained('boletas_gc')->cascadeOnDelete();
            $table->decimal('monto', 15, 2);
            $table->date('fecha_pago');
            $table->enum('medio_pago', ['efectivo', 'transferencia', 'cheque', 'tarjeta', 'pac', 'webpay', 'otro'])->default('transferencia');
            $table->string('referencia', 100)->nullable();
            $table->string('banco', 100)->nullable();
            $table->string('numero_operacion', 50)->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['confirmado', 'pendiente', 'rechazado', 'anulado'])->default('confirmado');
            $table->string('comprobante')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'fecha_pago']);
            $table->index('boleta_id');
        });

        // ========================================
        // FONDO DE RESERVA
        // ========================================
        Schema::create('fondo_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->enum('tipo_movimiento', ['ingreso', 'egreso', 'ajuste']);
            $table->string('concepto', 200);
            $table->decimal('monto', 15, 2);
            $table->decimal('saldo', 15, 2);
            $table->text('observaciones')->nullable();
            $table->string('documento_respaldo')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['edificio_id', 'fecha']);
        });

        // ========================================
        // CONVENIOS DE PAGO
        // ========================================
        Schema::create('convenios_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->decimal('deuda_original', 15, 2);
            $table->decimal('deuda_convenida', 15, 2);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->integer('numero_cuotas');
            $table->decimal('monto_cuota', 15, 2);
            $table->date('fecha_inicio');
            $table->integer('dia_pago')->default(10);
            $table->decimal('tasa_interes', 5, 2)->default(0);
            $table->enum('estado', ['vigente', 'cumplido', 'incumplido', 'anulado'])->default('vigente');
            $table->text('observaciones')->nullable();
            $table->string('documento')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
        });

        Schema::create('cuotas_convenio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convenio_id')->constrained('convenios_pago')->cascadeOnDelete();
            $table->integer('numero_cuota');
            $table->date('fecha_vencimiento');
            $table->decimal('monto', 15, 2);
            $table->decimal('monto_pagado', 15, 2)->default(0);
            $table->date('fecha_pago')->nullable();
            $table->enum('estado', ['pendiente', 'pagada', 'vencida'])->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas_convenio');
        Schema::dropIfExists('convenios_pago');
        Schema::dropIfExists('fondo_reserva');
        Schema::dropIfExists('pagos_gc');
        Schema::dropIfExists('cargos_gc');
        Schema::dropIfExists('boletas_gc');
        Schema::dropIfExists('presupuestos_gc');
        Schema::dropIfExists('periodos_gc');
        Schema::dropIfExists('conceptos_gc');
    }
};
