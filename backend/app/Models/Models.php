<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

// ========================================
// USER MODEL
// ========================================
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'rut', 'telefono', 'avatar', 'activo',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_login' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

// ========================================
// TENANT MODEL
// ========================================
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre', 'rut', 'razon_social', 'giro', 'direccion', 'comuna', 'region',
        'telefono', 'email', 'representante_legal', 'representante_rut', 'plan',
        'activo', 'configuracion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'configuracion' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function edificios(): HasMany
    {
        return $this->hasMany(Edificio::class);
    }
}

// ========================================
// EDIFICIO MODEL
// ========================================
class Edificio extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'nombre', 'direccion', 'comuna', 'region', 'rut', 'tipo',
        'total_unidades', 'pisos', 'subterraneos', 'fecha_constitucion', 'rol_principal',
        'administrador_nombre', 'administrador_rut', 'administrador_email', 'administrador_telefono',
        'administrador_desde', 'dia_vencimiento_gc', 'interes_mora', 'fondo_reserva_porcentaje',
        'moneda_default', 'logo', 'configuracion', 'activo',
    ];

    protected $casts = [
        'fecha_constitucion' => 'date',
        'administrador_desde' => 'date',
        'activo' => 'boolean',
        'configuracion' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(ContratoArriendo::class);
    }

    public function reuniones(): HasMany
    {
        return $this->hasMany(Reunion::class);
    }
}

// ========================================
// PERSONA MODEL
// ========================================
class Persona extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'rut', 'tipo_persona', 'nombre', 'apellido_paterno',
        'apellido_materno', 'razon_social', 'email', 'telefono', 'telefono_secundario',
        'direccion', 'comuna', 'fecha_nacimiento', 'sexo', 'nacionalidad', 'estado_civil',
        'banco', 'tipo_cuenta', 'numero_cuenta', 'notas', 'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unidadesPropietario(): HasMany
    {
        return $this->hasMany(Unidad::class, 'propietario_id');
    }
}

// ========================================
// UNIDAD MODEL
// ========================================
class Unidad extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'unidades';

    protected $fillable = [
        'tenant_id', 'edificio_id', 'propietario_id', 'residente_id', 'numero', 'tipo',
        'piso', 'superficie_util', 'superficie_terraza', 'superficie_total', 'prorrateo',
        'rol_avaluo', 'avaluo_fiscal', 'coef_agua', 'coef_gas', 'coef_calefaccion',
        'dormitorios', 'banos', 'estacionamientos', 'bodegas', 'fecha_compra',
        'valor_compra', 'observaciones', 'activa',
    ];

    protected $casts = [
        'fecha_compra' => 'date',
        'activa' => 'boolean',
    ];

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'propietario_id');
    }

    public function residente(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'residente_id');
    }

    public function boletas(): HasMany
    {
        return $this->hasMany(BoletaGC::class);
    }
}

// ========================================
// GASTOS COMUNES MODELS
// ========================================
class PeriodoGC extends Model
{
    protected $table = 'periodos_gc';

    protected $fillable = [
        'tenant_id', 'edificio_id', 'mes', 'anio', 'fecha_emision', 'fecha_vencimiento',
        'fecha_segundo_vencimiento', 'estado', 'total_emitido', 'total_recaudado',
        'total_pendiente', 'observaciones', 'cerrado_at', 'cerrado_por',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_segundo_vencimiento' => 'date',
        'cerrado_at' => 'datetime',
    ];

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    public function boletas(): HasMany
    {
        return $this->hasMany(BoletaGC::class, 'periodo_id');
    }
}

class BoletaGC extends Model
{
    use SoftDeletes;

    protected $table = 'boletas_gc';

    protected $fillable = [
        'tenant_id', 'edificio_id', 'periodo_id', 'unidad_id', 'numero_boleta',
        'fecha_emision', 'fecha_vencimiento', 'fecha_segundo_vencimiento',
        'saldo_anterior', 'total_cargos', 'total_abonos', 'total_intereses',
        'total_a_pagar', 'estado', 'dias_mora', 'observaciones', 'archivo_pdf', 'enviada_at',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_segundo_vencimiento' => 'date',
        'enviada_at' => 'datetime',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoGC::class, 'periodo_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(CargoGC::class, 'boleta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoGC::class, 'boleta_id');
    }
}

class CargoGC extends Model
{
    protected $table = 'cargos_gc';

    protected $fillable = [
        'boleta_id', 'concepto_id', 'descripcion', 'monto', 'tipo', 'cantidad', 'precio_unitario',
    ];

    public function boleta(): BelongsTo
    {
        return $this->belongsTo(BoletaGC::class, 'boleta_id');
    }
}

class PagoGC extends Model
{
    use SoftDeletes;

    protected $table = 'pagos_gc';

    protected $fillable = [
        'tenant_id', 'boleta_id', 'monto', 'fecha_pago', 'medio_pago', 'referencia',
        'banco', 'numero_operacion', 'observaciones', 'estado', 'comprobante', 'registrado_por',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
    ];

    public function boleta(): BelongsTo
    {
        return $this->belongsTo(BoletaGC::class, 'boleta_id');
    }
}

// ========================================
// ARRIENDOS MODELS
// ========================================
class ContratoArriendo extends Model
{
    use SoftDeletes;

    protected $table = 'contratos_arriendo';

    protected $fillable = [
        'tenant_id', 'edificio_id', 'arrendatario_id', 'numero_contrato', 'tipo_espacio',
        'ubicacion_espacio', 'superficie_m2', 'descripcion_espacio', 'fecha_inicio',
        'fecha_termino', 'duracion_meses', 'renovacion_automatica', 'preaviso_dias',
        'monto_mensual', 'moneda', 'dia_facturacion', 'dias_pago', 'reajuste_tipo',
        'reajuste_porcentaje', 'reajuste_cada_meses', 'ultimo_reajuste', 'garantia_monto',
        'garantia_tipo', 'garantia_vencimiento', 'estado', 'fecha_termino_real',
        'motivo_termino', 'documento_contrato', 'clausulas_especiales', 'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'ultimo_reajuste' => 'date',
        'garantia_vencimiento' => 'date',
        'fecha_termino_real' => 'date',
        'renovacion_automatica' => 'boolean',
    ];

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    public function arrendatario(): BelongsTo
    {
        return $this->belongsTo(Arrendatario::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(FacturaArriendo::class, 'contrato_id');
    }
}

class Arrendatario extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'rut', 'razon_social', 'nombre_fantasia', 'giro', 'direccion',
        'comuna', 'telefono', 'email', 'contacto_nombre', 'contacto_telefono',
        'contacto_email', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}

class FacturaArriendo extends Model
{
    use SoftDeletes;

    protected $table = 'facturas_arriendo';

    protected $fillable = [
        'tenant_id', 'edificio_id', 'contrato_id', 'numero_factura', 'folio_sii',
        'periodo_mes', 'periodo_anio', 'fecha_emision', 'fecha_vencimiento',
        'monto_neto', 'iva', 'monto_total', 'monto_uf', 'valor_uf_usado', 'estado',
        'fecha_pago', 'monto_pagado', 'medio_pago', 'referencia_pago', 'archivo_pdf',
        'archivo_xml', 'respuesta_sii', 'enviada_sii_at', 'observaciones',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_pago' => 'date',
        'enviada_sii_at' => 'datetime',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoArriendo::class, 'contrato_id');
    }
}

// ========================================
// DISTRIBUCIÓN MODELS
// ========================================
class Distribucion extends Model
{
    use SoftDeletes;

    protected $table = 'distribuciones';

    protected $fillable = [
        'tenant_id', 'edificio_id', 'contrato_id', 'factura_id', 'periodo_mes',
        'periodo_anio', 'concepto', 'monto_bruto', 'gastos_administracion',
        'porcentaje_administracion', 'otros_descuentos', 'monto_neto',
        'metodo_distribucion', 'estado', 'total_beneficiarios', 'aprobado_por',
        'aprobada_at', 'procesada_por', 'procesada_at', 'observaciones',
    ];

    protected $casts = [
        'aprobada_at' => 'datetime',
        'procesada_at' => 'datetime',
    ];

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DistribucionDetalle::class);
    }
}

class DistribucionDetalle extends Model
{
    protected $table = 'distribucion_detalle';

    protected $fillable = [
        'distribucion_id', 'unidad_id', 'beneficiario_id', 'porcentaje_participacion',
        'monto_bruto', 'retencion_impuesto', 'monto_neto', 'forma_pago', 'pagado',
        'fecha_pago', 'referencia_pago', 'certificado_generado', 'certificado_url', 'certificado_at',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'certificado_at' => 'datetime',
        'pagado' => 'boolean',
        'certificado_generado' => 'boolean',
    ];

    public function distribucion(): BelongsTo
    {
        return $this->belongsTo(Distribucion::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'beneficiario_id');
    }
}

// ========================================
// RRHH MODELS
// ========================================
class Empleado extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'edificio_id', 'user_id', 'rut', 'nombres', 'apellido_paterno',
        'apellido_materno', 'fecha_nacimiento', 'sexo', 'estado_civil', 'nacionalidad',
        'direccion', 'comuna', 'telefono', 'email', 'contacto_emergencia', 'telefono_emergencia',
        'cargo_id', 'fecha_ingreso', 'fecha_termino', 'tipo_contrato', 'fecha_termino_contrato',
        'jornada', 'horas_semanales', 'turno', 'sueldo_base', 'gratificacion', 'colacion',
        'movilizacion', 'asignacion_familiar', 'cargas_familiares', 'afp_id', 'salud_id',
        'uf_pactadas', 'afc', 'mutual_id', 'banco_id', 'tipo_cuenta', 'numero_cuenta',
        'estado', 'causal_termino', 'foto', 'observaciones',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_termino' => 'date',
        'fecha_termino_contrato' => 'date',
        'afc' => 'boolean',
    ];

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(Liquidacion::class);
    }
}

class Liquidacion extends Model
{
    protected $table = 'liquidaciones';

    protected $fillable = [
        'tenant_id', 'empleado_id', 'mes', 'anio', 'dias_trabajados', 'dias_licencia',
        'dias_vacaciones', 'dias_ausencia', 'sueldo_base', 'gratificacion',
        'horas_extras_50', 'monto_horas_extras_50', 'horas_extras_100', 'monto_horas_extras_100',
        'comisiones', 'bonos', 'asignacion_colacion', 'asignacion_movilizacion',
        'asignacion_familiar', 'otros_haberes', 'total_haberes', 'total_imponible',
        'total_tributable', 'afp', 'afp_tasa', 'salud', 'salud_tasa', 'seguro_cesantia',
        'impuesto_unico', 'total_descuentos_legales', 'anticipo', 'prestamos',
        'otros_descuentos', 'total_descuentos', 'sueldo_liquido', 'uf_valor', 'utm_valor',
        'tope_imponible', 'estado', 'fecha_pago', 'aprobada_por', 'archivo_pdf',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}

// ========================================
// REUNIONES MODELS
// ========================================
class Reunion extends Model
{
    use SoftDeletes;

    protected $table = 'reuniones';

    protected $fillable = [
        'tenant_id', 'edificio_id', 'uuid', 'titulo', 'descripcion', 'tipo', 'modalidad',
        'fecha_inicio', 'fecha_fin', 'duracion_minutos', 'lugar', 'quorum_requerido',
        'quorum_alcanzado', 'quorum_verificado', 'orden_del_dia', 'documentos_adjuntos',
        'sala_url', 'sala_password', 'grabar_reunion', 'grabacion_url',
        'transcribir_automatico', 'transcripcion', 'estado', 'convocada_at', 'iniciada_at',
        'finalizada_at', 'creada_por', 'presidida_por', 'secretario',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'convocada_at' => 'datetime',
        'iniciada_at' => 'datetime',
        'finalizada_at' => 'datetime',
        'documentos_adjuntos' => 'array',
        'grabar_reunion' => 'boolean',
        'transcribir_automatico' => 'boolean',
        'quorum_verificado' => 'boolean',
    ];

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    public function convocados(): HasMany
    {
        return $this->hasMany(ReunionConvocado::class);
    }

    public function votaciones(): HasMany
    {
        return $this->hasMany(Votacion::class);
    }
}

class ReunionConvocado extends Model
{
    protected $table = 'reunion_convocados';

    protected $fillable = [
        'reunion_id', 'unidad_id', 'persona_id', 'user_id', 'nombre', 'email',
        'prorrateo', 'confirmado', 'confirmado_at', 'poder_representacion',
        'representado_por', 'hora_entrada', 'hora_salida', 'presente', 'recordatorios_enviados',
    ];

    protected $casts = [
        'confirmado_at' => 'datetime',
        'hora_entrada' => 'datetime',
        'hora_salida' => 'datetime',
        'confirmado' => 'boolean',
        'poder_representacion' => 'boolean',
        'presente' => 'boolean',
        'recordatorios_enviados' => 'array',
    ];

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }
}

class Votacion extends Model
{
    protected $table = 'votaciones';

    protected $fillable = [
        'reunion_id', 'titulo', 'descripcion', 'texto_mocion', 'tipo', 'opciones',
        'quorum_tipo', 'ponderacion', 'duracion_segundos', 'voto_secreto', 'estado',
        'abierta_at', 'cerrada_at', 'resultados', 'aprobada', 'orden',
    ];

    protected $casts = [
        'opciones' => 'array',
        'resultados' => 'array',
        'abierta_at' => 'datetime',
        'cerrada_at' => 'datetime',
        'voto_secreto' => 'boolean',
        'aprobada' => 'boolean',
    ];

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }

    public function votos(): HasMany
    {
        return $this->hasMany(Voto::class);
    }
}

class Voto extends Model
{
    protected $fillable = [
        'votacion_id', 'convocado_id', 'voto', 'peso', 'emitido_at', 'ip',
    ];

    protected $casts = [
        'emitido_at' => 'datetime',
    ];
}

// ========================================
// CONTABILIDAD MODELS
// ========================================
class Asiento extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'edificio_id', 'numero', 'fecha', 'tipo', 'glosa',
        'documento_tipo', 'documento_numero', 'documento_fecha', 'total_debe',
        'total_haber', 'estado', 'creado_por', 'aprobado_por', 'aprobado_at',
        'es_automatico', 'origen_modulo', 'origen_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'documento_fecha' => 'date',
        'aprobado_at' => 'datetime',
        'es_automatico' => 'boolean',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(AsientoLinea::class);
    }
}

class AsientoLinea extends Model
{
    protected $table = 'asiento_lineas';

    protected $fillable = [
        'asiento_id', 'cuenta_id', 'centro_costo_id', 'glosa', 'debe', 'haber',
        'documento_referencia', 'orden',
    ];

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class);
    }
}

class PlanCuenta extends Model
{
    protected $table = 'plan_cuentas';

    protected $fillable = [
        'tenant_id', 'codigo', 'nombre', 'tipo', 'naturaleza', 'cuenta_padre_id',
        'nivel', 'es_cuenta_mayor', 'permite_movimientos', 'activa', 'descripcion', 'orden',
    ];

    protected $casts = [
        'es_cuenta_mayor' => 'boolean',
        'permite_movimientos' => 'boolean',
        'activa' => 'boolean',
    ];
}

class BoletaGC extends Model
{
    use SoftDeletes;

    protected $table = 'boletas_gc';

    protected $fillable = [
        'tenant_id', 'edificio_id', 'periodo_id', 'unidad_id', 'numero_boleta',
        'fecha_emision', 'fecha_vencimiento', 'fecha_segundo_vencimiento',
        'saldo_anterior', 'total_cargos', 'total_abonos', 'total_intereses',
        'total_a_pagar', 'estado', 'dias_mora', 'observaciones', 'archivo_pdf', 'enviada_at',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_segundo_vencimiento' => 'date',
        'enviada_at' => 'datetime',
        'saldo_anterior' => 'decimal:2',
        'total_cargos' => 'decimal:2',
        'total_abonos' => 'decimal:2',
        'total_intereses' => 'decimal:2',
        'total_a_pagar' => 'decimal:2',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoGC::class, 'periodo_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(CargoGC::class, 'boleta_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoGC::class, 'boleta_id');
    }

    public function edificio(): BelongsTo
    {
        return $this->belongsTo(Edificio::class);
    }

    /**
     * Calcular saldo pendiente
     */
    public function getSaldoPendienteAttribute(): float
    {
        return $this->total_a_pagar - ($this->total_abonos ?? 0);
    }

    /**
     * Verificar si está vencida
     */
    public function getEstaVencidaAttribute(): bool
    {
        return $this->fecha_vencimiento < now() && in_array($this->estado, ['pendiente', 'parcial']);
    }
}
