<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // PLAN DE CUENTAS CONTABLE
        // ========================================
        Schema::create('plan_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre', 150);
            $table->enum('tipo', ['activo', 'pasivo', 'patrimonio', 'ingreso', 'gasto', 'resultado']);
            $table->enum('naturaleza', ['deudora', 'acreedora']);
            $table->foreignId('cuenta_padre_id')->nullable()->constrained('plan_cuentas')->nullOnDelete();
            $table->integer('nivel')->default(1);
            $table->boolean('es_cuenta_mayor')->default(false);
            $table->boolean('permite_movimientos')->default(true);
            $table->boolean('activa')->default(true);
            $table->text('descripcion')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'codigo']);
            $table->index(['tenant_id', 'tipo']);
        });

        // ========================================
        // CENTROS DE COSTO
        // ========================================
        Schema::create('centros_costo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('codigo', 20);
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'codigo']);
        });

        // ========================================
        // ASIENTOS CONTABLES
        // ========================================
        Schema::create('asientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('numero', 20);
            $table->date('fecha');
            $table->enum('tipo', ['ingreso', 'egreso', 'traspaso', 'ajuste', 'apertura', 'cierre'])->default('traspaso');
            $table->string('glosa', 500);
            
            $table->string('documento_tipo', 50)->nullable();
            $table->string('documento_numero', 50)->nullable();
            $table->date('documento_fecha')->nullable();
            
            $table->decimal('total_debe', 18, 2)->default(0);
            $table->decimal('total_haber', 18, 2)->default(0);
            
            $table->enum('estado', ['borrador', 'contabilizado', 'anulado'])->default('borrador');
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            
            $table->boolean('es_automatico')->default(false);
            $table->string('origen_modulo', 50)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'fecha']);
            $table->index(['tenant_id', 'estado']);
        });

        // ========================================
        // LÍNEAS DE ASIENTO
        // ========================================
        Schema::create('asiento_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asiento_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cuenta_id')->constrained('plan_cuentas');
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();
            
            $table->string('glosa', 300)->nullable();
            $table->decimal('debe', 18, 2)->default(0);
            $table->decimal('haber', 18, 2)->default(0);
            
            $table->string('documento_referencia', 50)->nullable();
            $table->integer('orden')->default(0);
            
            $table->timestamps();

            $table->index('cuenta_id');
        });

        // ========================================
        // PERÍODOS CONTABLES
        // ========================================
        Schema::create('periodos_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('mes');
            $table->integer('anio');
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'edificio_id', 'mes', 'anio']);
        });

        // ========================================
        // REUNIONES
        // ========================================
        Schema::create('reuniones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['asamblea_ordinaria', 'asamblea_extraordinaria', 'comite_administracion', 'informativa', 'emergencia', 'otro'])->default('asamblea_ordinaria');
            $table->enum('modalidad', ['presencial', 'telematica', 'mixta'])->default('telematica');
            
            $table->datetime('fecha_inicio');
            $table->datetime('fecha_fin')->nullable();
            $table->integer('duracion_minutos')->nullable();
            $table->string('lugar', 200)->nullable();
            
            // Quórum
            $table->decimal('quorum_requerido', 5, 2)->nullable();
            $table->decimal('quorum_alcanzado', 5, 2)->nullable();
            $table->boolean('quorum_verificado')->default(false);
            
            // Orden del día y documentos
            $table->text('orden_del_dia')->nullable();
            $table->json('documentos_adjuntos')->nullable();
            
            // Configuración telemática
            $table->string('sala_url', 500)->nullable();
            $table->string('sala_password', 50)->nullable();
            $table->boolean('grabar_reunion')->default(true);
            $table->string('grabacion_url')->nullable();
            $table->boolean('transcribir_automatico')->default(false);
            $table->text('transcripcion')->nullable();
            
            // Estado
            $table->enum('estado', ['borrador', 'programada', 'convocada', 'en_curso', 'finalizada', 'cancelada'])->default('borrador');
            $table->timestamp('convocada_at')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('finalizada_at')->nullable();
            
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('presidida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('secretario')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'estado']);
            $table->index(['edificio_id', 'fecha_inicio']);
        });

        // ========================================
        // CONVOCADOS A REUNIÓN
        // ========================================
        Schema::create('reunion_convocados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades');
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('nombre', 200);
            $table->string('email', 255)->nullable();
            $table->decimal('prorrateo', 10, 6)->default(0);
            
            $table->boolean('confirmado')->default(false);
            $table->timestamp('confirmado_at')->nullable();
            $table->boolean('poder_representacion')->default(false);
            $table->string('representado_por')->nullable();
            
            $table->timestamp('hora_entrada')->nullable();
            $table->timestamp('hora_salida')->nullable();
            $table->boolean('presente')->default(false);
            
            $table->json('recordatorios_enviados')->nullable();
            $table->timestamps();

            $table->unique(['reunion_id', 'unidad_id']);
        });

        // ========================================
        // VOTACIONES
        // ========================================
        Schema::create('votaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->text('texto_mocion')->nullable();
            
            $table->enum('tipo', ['si_no', 'si_no_abstencion', 'opcion_multiple', 'ranking', 'abierta'])->default('si_no');
            $table->json('opciones')->nullable();
            
            $table->enum('quorum_tipo', ['mayoria_simple', 'mayoria_absoluta', 'dos_tercios', 'tres_cuartos', 'cuatro_quintos', 'unanimidad'])->default('mayoria_simple');
            $table->enum('ponderacion', ['por_persona', 'por_unidad', 'por_prorrateo'])->default('por_prorrateo');
            
            $table->integer('duracion_segundos')->nullable();
            $table->boolean('voto_secreto')->default(false);
            
            $table->enum('estado', ['pendiente', 'abierta', 'cerrada', 'anulada'])->default('pendiente');
            $table->timestamp('abierta_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            
            $table->json('resultados')->nullable();
            $table->boolean('aprobada')->nullable();
            
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index(['reunion_id', 'estado']);
        });

        // ========================================
        // VOTOS
        // ========================================
        Schema::create('votos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacion_id')->constrained('votaciones')->cascadeOnDelete();
            $table->foreignId('convocado_id')->constrained('reunion_convocados')->cascadeOnDelete();
            
            $table->string('voto', 100);
            $table->decimal('peso', 10, 6)->default(1);
            $table->timestamp('emitido_at');
            $table->string('ip', 45)->nullable();
            
            $table->timestamps();

            $table->unique(['votacion_id', 'convocado_id']);
        });

        // ========================================
        // ACTAS
        // ========================================
        Schema::create('actas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            
            $table->string('numero_acta', 30);
            $table->date('fecha');
            $table->text('contenido');
            
            $table->integer('asistentes_total')->default(0);
            $table->decimal('asistentes_prorrateo', 10, 2)->default(0);
            
            $table->json('acuerdos')->nullable();
            $table->json('tareas')->nullable();
            
            $table->string('archivo_pdf')->nullable();
            $table->enum('estado', ['borrador', 'firmada', 'publicada'])->default('borrador');
            
            $table->foreignId('redactada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ========================================
        // CATEGORÍAS CONSULTA LEGAL
        // ========================================
        Schema::create('categorias_legal', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('slug', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->string('icono', 50)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // ========================================
        // CONSULTAS LEGALES
        // ========================================
        Schema::create('consultas_legal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_legal')->nullOnDelete();
            
            $table->text('consulta');
            $table->text('respuesta')->nullable();
            $table->json('referencias_legales')->nullable();
            
            $table->integer('valoracion')->nullable();
            $table->boolean('util')->nullable();
            $table->text('feedback')->nullable();
            
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        // ========================================
        // INSTITUCIONES (PARA OFICIOS)
        // ========================================
        Schema::create('instituciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('sigla', 20)->nullable();
            $table->enum('tipo', ['municipalidad', 'ministerio', 'servicio_publico', 'superintendencia', 'tribunal', 'otro']);
            $table->string('direccion', 300)->nullable();
            $table->string('comuna', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('sitio_web', 255)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // ========================================
        // PLANTILLAS DE OFICIOS
        // ========================================
        Schema::create('plantillas_oficio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('codigo', 20)->unique();
            $table->enum('tipo', ['consulta', 'reclamo', 'denuncia', 'solicitud', 'fiscalizacion', 'otro']);
            $table->foreignId('institucion_id')->nullable()->constrained()->nullOnDelete();
            $table->text('contenido');
            $table->json('variables')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // ========================================
        // OFICIOS GENERADOS
        // ========================================
        Schema::create('oficios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plantilla_id')->nullable()->constrained('plantillas_oficio')->nullOnDelete();
            $table->foreignId('institucion_id')->constrained();
            
            $table->string('numero_oficio', 30);
            $table->date('fecha');
            $table->enum('tipo', ['consulta', 'reclamo', 'denuncia', 'solicitud', 'fiscalizacion', 'otro']);
            
            $table->string('asunto', 300);
            $table->text('contenido');
            $table->text('fundamentos_legales')->nullable();
            $table->text('peticion_concreta')->nullable();
            
            $table->json('adjuntos')->nullable();
            
            $table->enum('estado', ['borrador', 'enviado', 'respondido', 'cerrado', 'anulado'])->default('borrador');
            $table->date('fecha_envio')->nullable();
            $table->date('fecha_respuesta')->nullable();
            $table->text('respuesta')->nullable();
            
            $table->string('archivo_pdf')->nullable();
            $table->string('archivo_respuesta')->nullable();
            
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'estado']);
        });

        // ========================================
        // CERTIFICADOS DE CUMPLIMIENTO
        // ========================================
        Schema::create('certificados_cumplimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->constrained()->cascadeOnDelete();
            
            $table->string('numero_certificado', 30);
            $table->string('codigo_verificacion', 20)->unique();
            $table->date('fecha_emision');
            $table->date('fecha_validez')->nullable();
            
            $table->enum('tipo', ['cumplimiento_general', 'tributario', 'ley_21442', 'transparencia', 'deuda']);
            $table->string('titulo', 200);
            $table->text('contenido');
            
            $table->json('datos_certificados')->nullable();
            $table->string('archivo_pdf')->nullable();
            
            $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['edificio_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_cumplimiento');
        Schema::dropIfExists('oficios');
        Schema::dropIfExists('plantillas_oficio');
        Schema::dropIfExists('instituciones');
        Schema::dropIfExists('consultas_legal');
        Schema::dropIfExists('categorias_legal');
        Schema::dropIfExists('actas');
        Schema::dropIfExists('votos');
        Schema::dropIfExists('votaciones');
        Schema::dropIfExists('reunion_convocados');
        Schema::dropIfExists('reuniones');
        Schema::dropIfExists('periodos_contables');
        Schema::dropIfExists('asiento_lineas');
        Schema::dropIfExists('asientos');
        Schema::dropIfExists('centros_costo');
        Schema::dropIfExists('plan_cuentas');
    }
};
