<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;

// ========================================
// TAREAS PROGRAMADAS
// ========================================

// Actualizar días de mora diariamente a las 00:30
Schedule::command('mora:actualizar')->dailyAt('00:30');

// Generar facturas de arriendo el día 1 de cada mes a las 02:00
Schedule::call(function () {
    $contratos = DB::table('contratos_arriendo')
        ->where('estado', 'activo')
        ->where('dia_facturacion', now()->day)
        ->get();
    
    foreach ($contratos as $contrato) {
        // Llamar al controlador para generar factura
        // Esto debería estar en un Job para mejor manejo
    }
})->monthlyOn(1, '02:00');

// Enviar recordatorios de pago 3 días antes del vencimiento
Schedule::call(function () {
    $fechaLimite = now()->addDays(3)->toDateString();
    
    $boletas = DB::table('boletas_gc')
        ->join('unidades', 'boletas_gc.unidad_id', '=', 'unidades.id')
        ->join('personas', 'unidades.propietario_id', '=', 'personas.id')
        ->where('boletas_gc.estado', 'pendiente')
        ->where('boletas_gc.fecha_vencimiento', $fechaLimite)
        ->whereNotNull('personas.email')
        ->select('boletas_gc.*', 'personas.email', 'personas.nombre_completo', 'unidades.numero')
        ->get();
    
    foreach ($boletas as $boleta) {
        // Enviar email recordatorio
        // TODO: Implementar envío de emails
        \Log::info("Recordatorio enviado: {$boleta->email} - Boleta {$boleta->numero_boleta}");
    }
})->daily();

// Backup de base de datos diario a las 03:00
Schedule::command('backup:run --only-db')->dailyAt('03:00');

// Limpiar logs antiguos cada semana
Schedule::command('log:clear')->weekly();

// Generar reportes mensuales automáticos
Schedule::call(function () {
    // Generar balance general del mes anterior
    // TODO: Implementar generación automática
})->monthlyOn(1, '04:00');

// ========================================
// COMANDOS ARTISAN PERSONALIZADOS
// ========================================

Artisan::command('datapolis:install', function () {
    $this->info('Instalando DATAPOLIS PRO...');
    
    $this->call('migrate');
    $this->call('db:seed');
    $this->call('storage:link');
    
    $this->info('✓ DATAPOLIS PRO instalado correctamente');
})->purpose('Instalar y configurar DATAPOLIS PRO');

Artisan::command('datapolis:reset-demo', function () {
    $this->warn('⚠️  Esto eliminará todos los datos demo');
    
    if ($this->confirm('¿Continuar?')) {
        $this->call('migrate:fresh', ['--seed' => true]);
        $this->info('✓ Base de datos reiniciada con datos demo');
    }
})->purpose('Reiniciar base de datos con datos demo');
