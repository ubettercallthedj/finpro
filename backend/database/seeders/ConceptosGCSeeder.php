<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConceptosGCSeeder extends Seeder
{
    /**
     * Seed conceptos de gastos comunes comunes en Chile
     */
    public function run(): void
    {
        $conceptos = [
            // Ordinarios
            ['codigo' => 'GC-001', 'nombre' => 'Gastos Comunes Ordinarios', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 1],
            ['codigo' => 'GC-002', 'nombre' => 'Fondo de Reserva (5%)', 'tipo' => 'fondo_reserva', 'metodo_calculo' => 'prorrateo', 'orden' => 2],
            ['codigo' => 'GC-003', 'nombre' => 'Agua Potable', 'tipo' => 'ordinario', 'metodo_calculo' => 'consumo', 'orden' => 3],
            ['codigo' => 'GC-004', 'nombre' => 'Electricidad Áreas Comunes', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 4],
            ['codigo' => 'GC-005', 'nombre' => 'Gas', 'tipo' => 'ordinario', 'metodo_calculo' => 'consumo', 'orden' => 5],
            ['codigo' => 'GC-006', 'nombre' => 'Calefacción', 'tipo' => 'ordinario', 'metodo_calculo' => 'consumo', 'orden' => 6],
            ['codigo' => 'GC-007', 'nombre' => 'Administración', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 7],
            ['codigo' => 'GC-008', 'nombre' => 'Conserje', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 8],
            ['codigo' => 'GC-009', 'nombre' => 'Aseo', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 9],
            ['codigo' => 'GC-010', 'nombre' => 'Mantención Ascensores', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 10],
            ['codigo' => 'GC-011', 'nombre' => 'Mantención Jardines', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 11],
            ['codigo' => 'GC-012', 'nombre' => 'Seguro Incendio', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 12],
            ['codigo' => 'GC-013', 'nombre' => 'Seguro Terremoto', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 13],
            ['codigo' => 'GC-014', 'nombre' => 'Vigilancia', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 14],
            ['codigo' => 'GC-015', 'nombre' => 'Internet Áreas Comunes', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 15],
            ['codigo' => 'GC-016', 'nombre' => 'TV Cable Común', 'tipo' => 'ordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 16],
            
            // Extraordinarios
            ['codigo' => 'GE-001', 'nombre' => 'Reparación Fachada', 'tipo' => 'extraordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 50],
            ['codigo' => 'GE-002', 'nombre' => 'Reparación Techumbre', 'tipo' => 'extraordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 51],
            ['codigo' => 'GE-003', 'nombre' => 'Pintura General', 'tipo' => 'extraordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 52],
            ['codigo' => 'GE-004', 'nombre' => 'Reposición Ascensor', 'tipo' => 'extraordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 53],
            ['codigo' => 'GE-005', 'nombre' => 'Reparación Estanque Agua', 'tipo' => 'extraordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 54],
            ['codigo' => 'GE-006', 'nombre' => 'Mejoramiento Áreas Comunes', 'tipo' => 'extraordinario', 'metodo_calculo' => 'prorrateo', 'orden' => 55],
            
            // Multas
            ['codigo' => 'ML-001', 'nombre' => 'Multa Atraso Pago', 'tipo' => 'multa', 'metodo_calculo' => 'manual', 'orden' => 80],
            ['codigo' => 'ML-002', 'nombre' => 'Multa Ruidos Molestos', 'tipo' => 'multa', 'metodo_calculo' => 'manual', 'orden' => 81],
            ['codigo' => 'ML-003', 'nombre' => 'Multa Mal Uso Áreas Comunes', 'tipo' => 'multa', 'metodo_calculo' => 'manual', 'orden' => 82],
            
            // Intereses
            ['codigo' => 'INT-001', 'nombre' => 'Interés por Mora', 'tipo' => 'interes', 'metodo_calculo' => 'manual', 'orden' => 90],
        ];

        // Insertar para tenant demo (id 1)
        // En producción, esto se ejecutaría por cada tenant al crearse
        foreach ($conceptos as $concepto) {
            DB::table('conceptos_gc')->insert(array_merge($concepto, [
                'tenant_id' => 1,
                'descripcion' => "Concepto: {$concepto['nombre']}",
                'aplica_iva' => false,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $this->command->info('✓ ' . count($conceptos) . ' conceptos de gastos comunes creados');
    }
}
