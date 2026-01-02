<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanCuentasSeeder extends Seeder
{
    /**
     * Plan de Cuentas Base para Comunidades (formato Chile)
     */
    public function run(): void
    {
        $cuentas = [
            // 1. ACTIVOS
            ['codigo' => '1', 'nombre' => 'ACTIVO', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 1, 'es_cuenta_mayor' => true, 'permite_movimientos' => false],
            ['codigo' => '1.1', 'nombre' => 'ACTIVO CIRCULANTE', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 2, 'padre' => '1', 'es_cuenta_mayor' => true, 'permite_movimientos' => false],
            ['codigo' => '1.1.1', 'nombre' => 'Caja', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.1'],
            ['codigo' => '1.1.2', 'nombre' => 'Bancos', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.1'],
            ['codigo' => '1.1.3', 'nombre' => 'Deudores Gastos Comunes', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.1'],
            ['codigo' => '1.1.4', 'nombre' => 'Arriendos por Cobrar', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.1'],
            ['codigo' => '1.1.5', 'nombre' => 'IVA Crédito Fiscal', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.1'],
            ['codigo' => '1.1.9', 'nombre' => 'Otros Activos Circulantes', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.1'],
            
            ['codigo' => '1.2', 'nombre' => 'ACTIVO FIJO', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 2, 'padre' => '1', 'es_cuenta_mayor' => true, 'permite_movimientos' => false],
            ['codigo' => '1.2.1', 'nombre' => 'Terrenos', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.2'],
            ['codigo' => '1.2.2', 'nombre' => 'Construcciones', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.2'],
            ['codigo' => '1.2.3', 'nombre' => 'Muebles y Útiles', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.2'],
            ['codigo' => '1.2.4', 'nombre' => 'Equipos', 'tipo' => 'activo', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '1.2'],
            ['codigo' => '1.2.9', 'nombre' => 'Depreciación Acumulada', 'tipo' => 'activo', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '1.2'],
            
            // 2. PASIVOS
            ['codigo' => '2', 'nombre' => 'PASIVO', 'tipo' => 'pasivo', 'naturaleza' => 'acreedora', 'nivel' => 1, 'es_cuenta_mayor' => true, 'permite_movimientos' => false],
            ['codigo' => '2.1', 'nombre' => 'PASIVO CIRCULANTE', 'tipo' => 'pasivo', 'naturaleza' => 'acreedora', 'nivel' => 2, 'padre' => '2', 'es_cuenta_mayor' => true, 'permite_movimientos' => false],
            ['codigo' => '2.1.1', 'nombre' => 'Proveedores', 'tipo' => 'pasivo', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '2.1'],
            ['codigo' => '2.1.2', 'nombre' => 'Remuneraciones por Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '2.1'],
            ['codigo' => '2.1.3', 'nombre' => 'Cotizaciones Previsionales por Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '2.1'],
            ['codigo' => '2.1.4', 'nombre' => 'Impuestos por Pagar', 'tipo' => 'pasivo', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '2.1'],
            ['codigo' => '2.1.5', 'nombre' => 'IVA Débito Fiscal', 'tipo' => 'pasivo', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '2.1'],
            ['codigo' => '2.1.6', 'nombre' => 'Arriendos Cobrados por Anticipado', 'tipo' => 'pasivo', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '2.1'],
            
            // 3. PATRIMONIO
            ['codigo' => '3', 'nombre' => 'PATRIMONIO', 'tipo' => 'patrimonio', 'naturaleza' => 'acreedora', 'nivel' => 1, 'es_cuenta_mayor' => true, 'permite_movimientos' => false],
            ['codigo' => '3.1', 'nombre' => 'Capital', 'tipo' => 'patrimonio', 'naturaleza' => 'acreedora', 'nivel' => 2, 'padre' => '3'],
            ['codigo' => '3.1.1', 'nombre' => 'Fondo Común', 'tipo' => 'patrimonio', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '3.1'],
            ['codigo' => '3.1.2', 'nombre' => 'Fondo de Reserva', 'tipo' => 'patrimonio', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '3.1'],
            ['codigo' => '3.2', 'nombre' => 'Resultados', 'tipo' => 'patrimonio', 'naturaleza' => 'acreedora', 'nivel' => 2, 'padre' => '3'],
            ['codigo' => '3.2.1', 'nombre' => 'Resultados Acumulados', 'tipo' => 'patrimonio', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '3.2'],
            ['codigo' => '3.2.2', 'nombre' => 'Resultado del Ejercicio', 'tipo' => 'patrimonio', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '3.2'],
            
            // 4. INGRESOS
            ['codigo' => '4', 'nombre' => 'INGRESOS', 'tipo' => 'ingreso', 'naturaleza' => 'acreedora', 'nivel' => 1, 'es_cuenta_mayor' => true, 'permite_movimientos' => false],
            ['codigo' => '4.1', 'nombre' => 'Ingresos Operacionales', 'tipo' => 'ingreso', 'naturaleza' => 'acreedora', 'nivel' => 2, 'padre' => '4'],
            ['codigo' => '4.1.1', 'nombre' => 'Gastos Comunes', 'tipo' => 'ingreso', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '4.1'],
            ['codigo' => '4.1.2', 'nombre' => 'Fondo de Reserva', 'tipo' => 'ingreso', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '4.1'],
            ['codigo' => '4.1.3', 'nombre' => 'Arriendos', 'tipo' => 'ingreso', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '4.1'],
            ['codigo' => '4.1.4', 'nombre' => 'Multas e Intereses', 'tipo' => 'ingreso', 'naturaleza' => 'acreedora', 'nivel' => 3, 'padre' => '4.1'],
            
            // 5. GASTOS
            ['codigo' => '5', 'nombre' => 'GASTOS', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 1, 'es_cuenta_mayor' => true, 'permite_movimientos' => false],
            ['codigo' => '5.1', 'nombre' => 'Gastos Operacionales', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 2, 'padre' => '5'],
            ['codigo' => '5.1.1', 'nombre' => 'Remuneraciones', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
            ['codigo' => '5.1.2', 'nombre' => 'Cotizaciones Previsionales', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
            ['codigo' => '5.1.3', 'nombre' => 'Servicios Básicos', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
            ['codigo' => '5.1.4', 'nombre' => 'Mantenciones', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
            ['codigo' => '5.1.5', 'nombre' => 'Seguros', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
            ['codigo' => '5.1.6', 'nombre' => 'Aseo y Ornato', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
            ['codigo' => '5.1.7', 'nombre' => 'Vigilancia', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
            ['codigo' => '5.1.8', 'nombre' => 'Administración', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
            ['codigo' => '5.1.9', 'nombre' => 'Depreciación', 'tipo' => 'gasto', 'naturaleza' => 'deudora', 'nivel' => 3, 'padre' => '5.1'],
        ];

        // Insertar con relaciones padre-hijo
        $idMap = [];
        
        foreach ($cuentas as $cuenta) {
            $padreId = isset($cuenta['padre']) && isset($idMap[$cuenta['padre']]) 
                ? $idMap[$cuenta['padre']] 
                : null;

            $id = DB::table('plan_cuentas')->insertGetId([
                'tenant_id' => 1, // Demo tenant
                'codigo' => $cuenta['codigo'],
                'nombre' => $cuenta['nombre'],
                'tipo' => $cuenta['tipo'],
                'naturaleza' => $cuenta['naturaleza'],
                'nivel' => $cuenta['nivel'],
                'cuenta_padre_id' => $padreId,
                'es_cuenta_mayor' => $cuenta['es_cuenta_mayor'] ?? false,
                'permite_movimientos' => $cuenta['permite_movimientos'] ?? true,
                'activa' => true,
                'orden' => array_search($cuenta, $cuentas) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $idMap[$cuenta['codigo']] = $id;
        }

        $this->command->info('✓ Plan de cuentas base creado: ' . count($cuentas) . ' cuentas');
    }
}
