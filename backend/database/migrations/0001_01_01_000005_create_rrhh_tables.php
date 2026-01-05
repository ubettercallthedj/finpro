<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========================================
        // TABLAS PARAMÉTRICAS RRHH
        // ========================================
        Schema::create('afp', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10);
            $table->string('nombre', 100);
            $table->decimal('tasa_trabajador', 5, 2);
            $table->decimal('tasa_sis', 5, 2)->default(1.53);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('isapres', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10);
            $table->string('nombre', 100);
            $table->enum('tipo', ['isapre', 'fonasa'])->default('isapre');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('bancos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10);
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('mutuales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10);
            $table->string('nombre', 100);
            $table->decimal('tasa', 5, 2)->default(0.93);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('tramos_impuesto', function (Blueprint $table) {
            $table->id();
            $table->integer('anio');
            $table->integer('mes');
            $table->integer('tramo');
            $table->decimal('desde_utm', 10, 2);
            $table->decimal('hasta_utm', 10, 2)->nullable();
            $table->decimal('factor', 8, 4);
            $table->decimal('rebaja_utm', 10, 2);
            $table->timestamps();

            $table->index(['anio', 'mes']);
        });

        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('nombre', 100);
            $table->enum('tipo', ['legal', 'irrenunciable', 'regional'])->default('legal');
            $table->string('region', 100)->nullable();
            $table->timestamps();

            $table->unique('fecha');
        });

        // ========================================
        // CARGOS Y DEPARTAMENTOS
        // ========================================
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('descripcion', 200)->nullable();
            $table->foreignId('jefe_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departamento_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->decimal('sueldo_minimo', 15, 2)->nullable();
            $table->decimal('sueldo_maximo', 15, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // ========================================
        // EMPLEADOS
        // ========================================
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edificio_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Datos personales
            $table->string('rut', 12);
            $table->string('nombres', 100);
            $table->string('apellido_paterno', 100);
            $table->string('apellido_materno', 100)->nullable();
            $table->string('nombre_completo', 300)->virtualAs("CONCAT_WS(' ', nombres, apellido_paterno, apellido_materno)");
            $table->date('fecha_nacimiento');
            $table->enum('sexo', ['M', 'F']);
            $table->string('estado_civil', 20)->nullable();
            $table->string('nacionalidad', 50)->default('Chilena');
            
            // Contacto
            $table->string('direccion', 300);
            $table->string('comuna', 100);
            $table->string('telefono', 20);
            $table->string('email', 255);
            $table->string('contacto_emergencia', 200)->nullable();
            $table->string('telefono_emergencia', 20)->nullable();
            
            // Laboral
            $table->foreignId('cargo_id')->nullable()->constrained()->nullOnDelete();
            $table->date('fecha_ingreso');
            $table->date('fecha_termino')->nullable();
            $table->enum('tipo_contrato', ['indefinido', 'plazo_fijo', 'por_obra', 'honorarios'])->default('indefinido');
            $table->date('fecha_termino_contrato')->nullable();
            $table->enum('jornada', ['completa', 'parcial', 'art_22'])->default('completa');
            $table->integer('horas_semanales')->default(45);
            $table->string('turno', 50)->nullable();
            
            // Remuneración
            $table->decimal('sueldo_base', 15, 2);
            $table->decimal('gratificacion', 15, 2)->default(0);
            $table->decimal('colacion', 15, 2)->default(0);
            $table->decimal('movilizacion', 15, 2)->default(0);
            $table->decimal('asignacion_familiar', 15, 2)->default(0);
            $table->integer('cargas_familiares')->default(0);
            
            // Previsión
            $table->foreignId('afp_id')->nullable()->constrained('afp')->nullOnDelete();
            $table->foreignId('salud_id')->nullable()->constrained('isapres')->nullOnDelete();
            $table->decimal('uf_pactadas', 8, 2)->nullable();
            $table->boolean('afc')->default(true);
            $table->foreignId('mutual_id')->nullable()->constrained('mutuales')->nullOnDelete();
            
            // Datos bancarios
            $table->foreignId('banco_id')->nullable()->constrained('bancos')->nullOnDelete();
            $table->enum('tipo_cuenta', ['corriente', 'vista', 'ahorro'])->nullable();
            $table->string('numero_cuenta', 30)->nullable();
            
            // Estado
            $table->enum('estado', ['activo', 'licencia', 'vacaciones', 'suspendido', 'desvinculado'])->default('activo');
            $table->string('causal_termino', 10)->nullable();
            
            $table->string('foto')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'rut']);
            $table->index(['tenant_id', 'estado']);
        });

        // ========================================
        // LIQUIDACIONES
        // ========================================
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained()->cascadeOnDelete();
            
            $table->integer('mes');
            $table->integer('anio');
            $table->integer('dias_trabajados');
            $table->integer('dias_licencia')->default(0);
            $table->integer('dias_vacaciones')->default(0);
            $table->integer('dias_ausencia')->default(0);
            
            // Haberes
            $table->decimal('sueldo_base', 15, 2);
            $table->decimal('gratificacion', 15, 2)->default(0);
            $table->decimal('horas_extras_50', 8, 2)->default(0);
            $table->decimal('monto_horas_extras_50', 15, 2)->default(0);
            $table->decimal('horas_extras_100', 8, 2)->default(0);
            $table->decimal('monto_horas_extras_100', 15, 2)->default(0);
            $table->decimal('comisiones', 15, 2)->default(0);
            $table->decimal('bonos', 15, 2)->default(0);
            $table->decimal('asignacion_colacion', 15, 2)->default(0);
            $table->decimal('asignacion_movilizacion', 15, 2)->default(0);
            $table->decimal('asignacion_familiar', 15, 2)->default(0);
            $table->decimal('otros_haberes', 15, 2)->default(0);
            $table->decimal('total_haberes', 15, 2);
            $table->decimal('total_imponible', 15, 2);
            $table->decimal('total_tributable', 15, 2);
            
            // Descuentos legales
            $table->decimal('afp', 15, 2)->default(0);
            $table->decimal('afp_tasa', 5, 2)->default(0);
            $table->decimal('salud', 15, 2)->default(0);
            $table->decimal('salud_tasa', 5, 2)->default(0);
            $table->decimal('seguro_cesantia', 15, 2)->default(0);
            $table->decimal('impuesto_unico', 15, 2)->default(0);
            $table->decimal('total_descuentos_legales', 15, 2);
            
            // Otros descuentos
            $table->decimal('anticipo', 15, 2)->default(0);
            $table->decimal('prestamos', 15, 2)->default(0);
            $table->decimal('otros_descuentos', 15, 2)->default(0);
            $table->decimal('total_descuentos', 15, 2);
            
            // Totales
            $table->decimal('sueldo_liquido', 15, 2);
            
            // Valores de referencia
            $table->decimal('uf_valor', 15, 2);
            $table->decimal('utm_valor', 15, 2);
            $table->decimal('tope_imponible', 15, 2);
            
            $table->enum('estado', ['borrador', 'aprobada', 'pagada', 'anulada'])->default('borrador');
            $table->date('fecha_pago')->nullable();
            $table->foreignId('aprobada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archivo_pdf')->nullable();
            
            $table->timestamps();

            $table->unique(['empleado_id', 'mes', 'anio']);
            $table->index(['tenant_id', 'mes', 'anio']);
        });

        // ========================================
        // DETALLE LIQUIDACIÓN (HABERES Y DESCUENTOS)
        // ========================================
        Schema::create('liquidacion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('liquidaciones')->cascadeOnDelete();
            $table->enum('tipo', ['haber', 'descuento']);
            $table->string('codigo', 20);
            $table->string('concepto', 100);
            $table->decimal('monto', 15, 2);
            $table->boolean('imponible')->default(false);
            $table->boolean('tributable')->default(false);
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // ========================================
        // VACACIONES
        // ========================================
        Schema::create('vacaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained()->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_termino');
            $table->integer('dias_habiles');
            $table->integer('dias_corridos');
            $table->enum('tipo', ['legal', 'progresivas', 'adicionales'])->default('legal');
            $table->enum('estado', ['solicitada', 'aprobada', 'rechazada', 'tomada', 'anulada'])->default('solicitada');
            $table->text('observaciones')->nullable();
            $table->foreignId('aprobada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobada_at')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'estado']);
        });

        // ========================================
        // ASISTENCIA
        // ========================================
        Schema::create('asistencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained()->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();
            $table->time('hora_entrada_colacion')->nullable();
            $table->time('hora_salida_colacion')->nullable();
            $table->decimal('horas_trabajadas', 5, 2)->nullable();
            $table->decimal('horas_extras', 5, 2)->default(0);
            $table->enum('estado', ['presente', 'ausente', 'licencia', 'vacaciones', 'permiso', 'feriado'])->default('presente');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'fecha']);
        });

        // ========================================
        // CONTRATOS (DOCUMENTOS)
        // ========================================
        Schema::create('contratos_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 50);
            $table->date('fecha_inicio');
            $table->date('fecha_termino')->nullable();
            $table->decimal('sueldo', 15, 2);
            $table->text('clausulas_adicionales')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->enum('estado', ['vigente', 'terminado', 'renovado'])->default('vigente');
            $table->timestamps();
        });

        // ========================================
        // FINIQUITOS
        // ========================================
        Schema::create('finiquitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained()->cascadeOnDelete();
            $table->date('fecha_termino');
            $table->string('causal', 10);
            $table->text('descripcion_causal')->nullable();
            
            $table->decimal('sueldo_proporcional', 15, 2)->default(0);
            $table->decimal('vacaciones_proporcionales', 15, 2)->default(0);
            $table->decimal('vacaciones_pendientes', 15, 2)->default(0);
            $table->decimal('gratificacion_proporcional', 15, 2)->default(0);
            $table->decimal('indemnizacion_anos', 15, 2)->default(0);
            $table->decimal('indemnizacion_aviso', 15, 2)->default(0);
            $table->decimal('otros_haberes', 15, 2)->default(0);
            $table->decimal('total_haberes', 15, 2);
            
            $table->decimal('descuentos', 15, 2)->default(0);
            $table->decimal('total_pagar', 15, 2);
            
            $table->date('fecha_pago')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->enum('estado', ['borrador', 'firmado', 'pagado'])->default('borrador');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finiquitos');
        Schema::dropIfExists('contratos_trabajo');
        Schema::dropIfExists('asistencia');
        Schema::dropIfExists('vacaciones');
        Schema::dropIfExists('liquidacion_detalle');
        Schema::dropIfExists('liquidaciones');
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('cargos');
        Schema::dropIfExists('departamentos');
        Schema::dropIfExists('feriados');
        Schema::dropIfExists('tramos_impuesto');
        Schema::dropIfExists('mutuales');
        Schema::dropIfExists('bancos');
        Schema::dropIfExists('isapres');
        Schema::dropIfExists('afp');
    }
};
