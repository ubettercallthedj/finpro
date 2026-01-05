<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            RolesPermisosSeeder::class,
            IndicadoresSeeder::class,
            AFPSeeder::class,
            IsapreSeeder::class,
            BancoSeeder::class,
            MutualSeeder::class,
            TramosImpuestoSeeder::class,
            FeriadosSeeder::class,
            ConceptosGCSeeder::class,
            CategoriasLegalSeeder::class,
            InstitucionesSeeder::class,
            PlantillasOficioSeeder::class,
            PlanCuentasSeeder::class,
        ]);
    }
}

// ========================================
// ROLES Y PERMISOS
// ========================================
class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = [
            // Edificios
            ['name' => 'edificios.ver', 'guard_name' => 'web', 'description' => 'Ver edificios'],
            ['name' => 'edificios.crear', 'guard_name' => 'web', 'description' => 'Crear edificios'],
            ['name' => 'edificios.editar', 'guard_name' => 'web', 'description' => 'Editar edificios'],
            ['name' => 'edificios.eliminar', 'guard_name' => 'web', 'description' => 'Eliminar edificios'],
            
            // Unidades
            ['name' => 'unidades.ver', 'guard_name' => 'web', 'description' => 'Ver unidades'],
            ['name' => 'unidades.crear', 'guard_name' => 'web', 'description' => 'Crear unidades'],
            ['name' => 'unidades.editar', 'guard_name' => 'web', 'description' => 'Editar unidades'],
            ['name' => 'unidades.eliminar', 'guard_name' => 'web', 'description' => 'Eliminar unidades'],
            
            // Gastos Comunes
            ['name' => 'gastos_comunes.ver', 'guard_name' => 'web', 'description' => 'Ver gastos comunes'],
            ['name' => 'gastos_comunes.crear', 'guard_name' => 'web', 'description' => 'Crear gastos comunes'],
            ['name' => 'gastos_comunes.editar', 'guard_name' => 'web', 'description' => 'Editar gastos comunes'],
            ['name' => 'gastos_comunes.registrar_pago', 'guard_name' => 'web', 'description' => 'Registrar pagos'],
            ['name' => 'gastos_comunes.anular', 'guard_name' => 'web', 'description' => 'Anular boletas/pagos'],
            
            // Arriendos
            ['name' => 'arriendos.ver', 'guard_name' => 'web', 'description' => 'Ver arriendos'],
            ['name' => 'arriendos.crear', 'guard_name' => 'web', 'description' => 'Crear contratos'],
            ['name' => 'arriendos.editar', 'guard_name' => 'web', 'description' => 'Editar contratos'],
            ['name' => 'arriendos.facturar', 'guard_name' => 'web', 'description' => 'Generar facturas'],
            
            // Distribución
            ['name' => 'distribucion.ver', 'guard_name' => 'web', 'description' => 'Ver distribución'],
            ['name' => 'distribucion.crear', 'guard_name' => 'web', 'description' => 'Crear distribución'],
            ['name' => 'distribucion.aprobar', 'guard_name' => 'web', 'description' => 'Aprobar distribución'],
            ['name' => 'distribucion.certificados', 'guard_name' => 'web', 'description' => 'Generar certificados'],
            
            // RRHH
            ['name' => 'rrhh.ver', 'guard_name' => 'web', 'description' => 'Ver RRHH'],
            ['name' => 'rrhh.crear', 'guard_name' => 'web', 'description' => 'Crear empleados'],
            ['name' => 'rrhh.editar', 'guard_name' => 'web', 'description' => 'Editar empleados'],
            ['name' => 'rrhh.liquidaciones', 'guard_name' => 'web', 'description' => 'Generar liquidaciones'],
            ['name' => 'rrhh.eliminar', 'guard_name' => 'web', 'description' => 'Eliminar empleados'],
            
            // Contabilidad
            ['name' => 'contabilidad.ver', 'guard_name' => 'web', 'description' => 'Ver contabilidad'],
            ['name' => 'contabilidad.asientos', 'guard_name' => 'web', 'description' => 'Crear asientos'],
            ['name' => 'contabilidad.cierre', 'guard_name' => 'web', 'description' => 'Cierre contable'],
            ['name' => 'contabilidad.eliminar', 'guard_name' => 'web', 'description' => 'Eliminar asientos'],
            
            // Reuniones
            ['name' => 'reuniones.ver', 'guard_name' => 'web', 'description' => 'Ver reuniones'],
            ['name' => 'reuniones.crear', 'guard_name' => 'web', 'description' => 'Crear reuniones'],
            ['name' => 'reuniones.editar', 'guard_name' => 'web', 'description' => 'Editar reuniones'],
            ['name' => 'reuniones.gestionar', 'guard_name' => 'web', 'description' => 'Gestionar reuniones'],
            ['name' => 'reuniones.votaciones', 'guard_name' => 'web', 'description' => 'Crear votaciones'],
            ['name' => 'reuniones.actas', 'guard_name' => 'web', 'description' => 'Generar actas'],
            
            // Legal
            ['name' => 'legal.consultas', 'guard_name' => 'web', 'description' => 'Consultas legales'],
            ['name' => 'oficios.ver', 'guard_name' => 'web', 'description' => 'Ver oficios'],
            ['name' => 'oficios.crear', 'guard_name' => 'web', 'description' => 'Crear oficios'],
            ['name' => 'certificados.generar', 'guard_name' => 'web', 'description' => 'Generar certificados'],
            
            // Reportes
            ['name' => 'reportes.ver', 'guard_name' => 'web', 'description' => 'Ver reportes'],
            ['name' => 'reportes.exportar', 'guard_name' => 'web', 'description' => 'Exportar reportes'],
            
            // Configuración
            ['name' => 'configuracion.ver', 'guard_name' => 'web', 'description' => 'Ver configuración'],
            ['name' => 'configuracion.editar', 'guard_name' => 'web', 'description' => 'Editar configuración'],
            ['name' => 'usuarios.gestionar', 'guard_name' => 'web', 'description' => 'Gestionar usuarios'],
        ];

        foreach ($permisos as $permiso) {
            DB::table('permissions')->insert(array_merge($permiso, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        $roles = [
            ['name' => 'super_admin', 'guard_name' => 'web', 'description' => 'Super Administrador'],
            ['name' => 'admin', 'guard_name' => 'web', 'description' => 'Administrador'],
            ['name' => 'contador', 'guard_name' => 'web', 'description' => 'Contador'],
            ['name' => 'asistente', 'guard_name' => 'web', 'description' => 'Asistente Administrativo'],
            ['name' => 'conserje', 'guard_name' => 'web', 'description' => 'Conserje'],
            ['name' => 'copropietario', 'guard_name' => 'web', 'description' => 'Copropietario'],
            ['name' => 'comite', 'guard_name' => 'web', 'description' => 'Comité de Administración'],
        ];

        foreach ($roles as $rol) {
            DB::table('roles')->insert(array_merge($rol, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Asignar todos los permisos a super_admin
        $allPermissions = DB::table('permissions')->pluck('id');
        $superAdminRole = DB::table('roles')->where('name', 'super_admin')->first();
        
        foreach ($allPermissions as $permissionId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permissionId,
                'role_id' => $superAdminRole->id,
            ]);
        }
    }
}

// ========================================
// INDICADORES ECONÓMICOS
// ========================================
class IndicadoresSeeder extends Seeder
{
    public function run(): void
    {
        $indicadores = [
            ['codigo' => 'UF', 'fecha' => '2025-01-01', 'valor' => 38264.48, 'fuente' => 'SII'],
            ['codigo' => 'UF', 'fecha' => '2025-12-01', 'valor' => 38500.00, 'fuente' => 'Estimado'],
            ['codigo' => 'UTM', 'fecha' => '2025-01-01', 'valor' => 66362.00, 'fuente' => 'SII'],
            ['codigo' => 'UTM', 'fecha' => '2025-12-01', 'valor' => 67500.00, 'fuente' => 'Estimado'],
            ['codigo' => 'IPC', 'fecha' => '2025-01-01', 'valor' => 0.3, 'fuente' => 'INE'],
        ];

        foreach ($indicadores as $ind) {
            DB::table('indicadores_economicos')->insert(array_merge($ind, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// AFP
// ========================================
class AFPSeeder extends Seeder
{
    public function run(): void
    {
        $afps = [
            ['codigo' => 'CAPITAL', 'nombre' => 'AFP Capital', 'tasa_trabajador' => 11.44, 'tasa_sis' => 1.53],
            ['codigo' => 'CUPRUM', 'nombre' => 'AFP Cuprum', 'tasa_trabajador' => 11.44, 'tasa_sis' => 1.53],
            ['codigo' => 'HABITAT', 'nombre' => 'AFP Habitat', 'tasa_trabajador' => 11.27, 'tasa_sis' => 1.53],
            ['codigo' => 'MODELO', 'nombre' => 'AFP Modelo', 'tasa_trabajador' => 10.58, 'tasa_sis' => 1.53],
            ['codigo' => 'PLANVITAL', 'nombre' => 'AFP PlanVital', 'tasa_trabajador' => 11.16, 'tasa_sis' => 1.53],
            ['codigo' => 'PROVIDA', 'nombre' => 'AFP ProVida', 'tasa_trabajador' => 11.45, 'tasa_sis' => 1.53],
            ['codigo' => 'UNO', 'nombre' => 'AFP Uno', 'tasa_trabajador' => 10.49, 'tasa_sis' => 1.53],
        ];

        foreach ($afps as $afp) {
            DB::table('afp')->insert(array_merge($afp, [
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// ISAPRES
// ========================================
class IsapreSeeder extends Seeder
{
    public function run(): void
    {
        $isapres = [
            ['codigo' => 'FONASA', 'nombre' => 'FONASA', 'tipo' => 'fonasa'],
            ['codigo' => 'BANMEDICA', 'nombre' => 'Isapre Banmédica', 'tipo' => 'isapre'],
            ['codigo' => 'COLMENA', 'nombre' => 'Isapre Colmena', 'tipo' => 'isapre'],
            ['codigo' => 'CONSALUD', 'nombre' => 'Isapre Consalud', 'tipo' => 'isapre'],
            ['codigo' => 'CRUZBLANCA', 'nombre' => 'Isapre Cruz Blanca', 'tipo' => 'isapre'],
            ['codigo' => 'MASVIDA', 'nombre' => 'Isapre Nueva Masvida', 'tipo' => 'isapre'],
            ['codigo' => 'VIDATRES', 'nombre' => 'Isapre Vida Tres', 'tipo' => 'isapre'],
        ];

        foreach ($isapres as $isapre) {
            DB::table('isapres')->insert(array_merge($isapre, [
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// BANCOS
// ========================================
class BancoSeeder extends Seeder
{
    public function run(): void
    {
        $bancos = [
            ['codigo' => '001', 'nombre' => 'Banco de Chile'],
            ['codigo' => '009', 'nombre' => 'Banco Internacional'],
            ['codigo' => '012', 'nombre' => 'Banco Estado'],
            ['codigo' => '014', 'nombre' => 'Scotiabank Chile'],
            ['codigo' => '016', 'nombre' => 'Banco de Crédito e Inversiones (BCI)'],
            ['codigo' => '028', 'nombre' => 'Banco Bice'],
            ['codigo' => '031', 'nombre' => 'HSBC Bank Chile'],
            ['codigo' => '037', 'nombre' => 'Banco Santander Chile'],
            ['codigo' => '039', 'nombre' => 'Itaú Corpbanca'],
            ['codigo' => '049', 'nombre' => 'Banco Security'],
            ['codigo' => '051', 'nombre' => 'Banco Falabella'],
            ['codigo' => '053', 'nombre' => 'Banco Ripley'],
            ['codigo' => '055', 'nombre' => 'Banco Consorcio'],
            ['codigo' => '504', 'nombre' => 'Banco BBVA Chile'],
        ];

        foreach ($bancos as $banco) {
            DB::table('bancos')->insert(array_merge($banco, [
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// MUTUALES
// ========================================
class MutualSeeder extends Seeder
{
    public function run(): void
    {
        $mutuales = [
            ['codigo' => 'ACHS', 'nombre' => 'Asociación Chilena de Seguridad', 'tasa' => 0.93],
            ['codigo' => 'MUTUAL', 'nombre' => 'Mutual de Seguridad C.Ch.C.', 'tasa' => 0.93],
            ['codigo' => 'IST', 'nombre' => 'Instituto de Seguridad del Trabajo', 'tasa' => 0.93],
            ['codigo' => 'ISL', 'nombre' => 'Instituto de Seguridad Laboral', 'tasa' => 0.93],
        ];

        foreach ($mutuales as $mutual) {
            DB::table('mutuales')->insert(array_merge($mutual, [
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// TRAMOS DE IMPUESTO 2025
// ========================================
class TramosImpuestoSeeder extends Seeder
{
    public function run(): void
    {
        $tramos = [
            ['tramo' => 1, 'desde_utm' => 0, 'hasta_utm' => 13.5, 'factor' => 0, 'rebaja_utm' => 0],
            ['tramo' => 2, 'desde_utm' => 13.5, 'hasta_utm' => 30, 'factor' => 0.04, 'rebaja_utm' => 0.54],
            ['tramo' => 3, 'desde_utm' => 30, 'hasta_utm' => 50, 'factor' => 0.08, 'rebaja_utm' => 1.74],
            ['tramo' => 4, 'desde_utm' => 50, 'hasta_utm' => 70, 'factor' => 0.135, 'rebaja_utm' => 4.49],
            ['tramo' => 5, 'desde_utm' => 70, 'hasta_utm' => 90, 'factor' => 0.23, 'rebaja_utm' => 11.14],
            ['tramo' => 6, 'desde_utm' => 90, 'hasta_utm' => 120, 'factor' => 0.304, 'rebaja_utm' => 17.80],
            ['tramo' => 7, 'desde_utm' => 120, 'hasta_utm' => 310, 'factor' => 0.35, 'rebaja_utm' => 23.32],
            ['tramo' => 8, 'desde_utm' => 310, 'hasta_utm' => null, 'factor' => 0.40, 'rebaja_utm' => 38.82],
        ];

        foreach ($tramos as $tramo) {
            DB::table('tramos_impuesto')->insert(array_merge($tramo, [
                'anio' => 2025,
                'mes' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// FERIADOS 2025-2026
// ========================================
class FeriadosSeeder extends Seeder
{
    public function run(): void
    {
        $feriados = [
            // 2025
            ['fecha' => '2025-01-01', 'nombre' => 'Año Nuevo', 'tipo' => 'irrenunciable'],
            ['fecha' => '2025-04-18', 'nombre' => 'Viernes Santo', 'tipo' => 'irrenunciable'],
            ['fecha' => '2025-04-19', 'nombre' => 'Sábado Santo', 'tipo' => 'legal'],
            ['fecha' => '2025-05-01', 'nombre' => 'Día del Trabajo', 'tipo' => 'irrenunciable'],
            ['fecha' => '2025-05-21', 'nombre' => 'Día de las Glorias Navales', 'tipo' => 'legal'],
            ['fecha' => '2025-06-20', 'nombre' => 'Día Nacional de los Pueblos Indígenas', 'tipo' => 'legal'],
            ['fecha' => '2025-06-29', 'nombre' => 'San Pedro y San Pablo', 'tipo' => 'legal'],
            ['fecha' => '2025-07-16', 'nombre' => 'Día de la Virgen del Carmen', 'tipo' => 'legal'],
            ['fecha' => '2025-08-15', 'nombre' => 'Asunción de la Virgen', 'tipo' => 'legal'],
            ['fecha' => '2025-09-18', 'nombre' => 'Independencia Nacional', 'tipo' => 'irrenunciable'],
            ['fecha' => '2025-09-19', 'nombre' => 'Día de las Glorias del Ejército', 'tipo' => 'irrenunciable'],
            ['fecha' => '2025-10-12', 'nombre' => 'Encuentro de Dos Mundos', 'tipo' => 'legal'],
            ['fecha' => '2025-10-31', 'nombre' => 'Día de las Iglesias Evangélicas', 'tipo' => 'legal'],
            ['fecha' => '2025-11-01', 'nombre' => 'Día de Todos los Santos', 'tipo' => 'legal'],
            ['fecha' => '2025-12-08', 'nombre' => 'Inmaculada Concepción', 'tipo' => 'legal'],
            ['fecha' => '2025-12-25', 'nombre' => 'Navidad', 'tipo' => 'irrenunciable'],
            // 2026
            ['fecha' => '2026-01-01', 'nombre' => 'Año Nuevo', 'tipo' => 'irrenunciable'],
            ['fecha' => '2026-04-03', 'nombre' => 'Viernes Santo', 'tipo' => 'irrenunciable'],
            ['fecha' => '2026-04-04', 'nombre' => 'Sábado Santo', 'tipo' => 'legal'],
            ['fecha' => '2026-05-01', 'nombre' => 'Día del Trabajo', 'tipo' => 'irrenunciable'],
            ['fecha' => '2026-05-21', 'nombre' => 'Día de las Glorias Navales', 'tipo' => 'legal'],
            ['fecha' => '2026-06-21', 'nombre' => 'Día Nacional de los Pueblos Indígenas', 'tipo' => 'legal'],
            ['fecha' => '2026-06-29', 'nombre' => 'San Pedro y San Pablo', 'tipo' => 'legal'],
            ['fecha' => '2026-07-16', 'nombre' => 'Día de la Virgen del Carmen', 'tipo' => 'legal'],
            ['fecha' => '2026-08-15', 'nombre' => 'Asunción de la Virgen', 'tipo' => 'legal'],
            ['fecha' => '2026-09-18', 'nombre' => 'Independencia Nacional', 'tipo' => 'irrenunciable'],
            ['fecha' => '2026-09-19', 'nombre' => 'Día de las Glorias del Ejército', 'tipo' => 'irrenunciable'],
            ['fecha' => '2026-10-12', 'nombre' => 'Encuentro de Dos Mundos', 'tipo' => 'legal'],
            ['fecha' => '2026-10-31', 'nombre' => 'Día de las Iglesias Evangélicas', 'tipo' => 'legal'],
            ['fecha' => '2026-11-01', 'nombre' => 'Día de Todos los Santos', 'tipo' => 'legal'],
            ['fecha' => '2026-12-08', 'nombre' => 'Inmaculada Concepción', 'tipo' => 'legal'],
            ['fecha' => '2026-12-25', 'nombre' => 'Navidad', 'tipo' => 'irrenunciable'],
        ];

        foreach ($feriados as $feriado) {
            DB::table('feriados')->insert(array_merge($feriado, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// CONCEPTOS GASTOS COMUNES
// ========================================
class ConceptosGCSeeder extends Seeder
{
    public function run(): void
    {
        // Este seeder se ejecutará por tenant, aquí solo estructura base
    }
}

// ========================================
// CATEGORÍAS LEGALES
// ========================================
class CategoriasLegalSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Copropiedad Inmobiliaria', 'slug' => 'copropiedad', 'descripcion' => 'Ley 21.442 y normativa de copropiedad', 'icono' => 'building', 'orden' => 1],
            ['nombre' => 'Gastos Comunes', 'slug' => 'gastos-comunes', 'descripcion' => 'Cobros, morosidad, fondo de reserva', 'icono' => 'currency', 'orden' => 2],
            ['nombre' => 'Asambleas y Votaciones', 'slug' => 'asambleas', 'descripcion' => 'Quórum, actas, reuniones telemáticas', 'icono' => 'users', 'orden' => 3],
            ['nombre' => 'Administración', 'slug' => 'administracion', 'descripcion' => 'Deberes y facultades del administrador', 'icono' => 'briefcase', 'orden' => 4],
            ['nombre' => 'Arriendos y Contratos', 'slug' => 'arriendos', 'descripcion' => 'Contratos de arriendo de espacios comunes', 'icono' => 'document', 'orden' => 5],
            ['nombre' => 'Tributario', 'slug' => 'tributario', 'descripcion' => 'Ley 21.713, distribución de ingresos, certificados', 'icono' => 'calculator', 'orden' => 6],
            ['nombre' => 'Laboral', 'slug' => 'laboral', 'descripcion' => 'Contratos, liquidaciones, finiquitos', 'icono' => 'id-card', 'orden' => 7],
            ['nombre' => 'Ruidos y Convivencia', 'slug' => 'convivencia', 'descripcion' => 'Multas, sanciones, reglamento interno', 'icono' => 'volume', 'orden' => 8],
        ];

        foreach ($categorias as $cat) {
            DB::table('categorias_legal')->insert(array_merge($cat, [
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// INSTITUCIONES
// ========================================
class InstitucionesSeeder extends Seeder
{
    public function run(): void
    {
        $instituciones = [
            ['nombre' => 'Servicio de Impuestos Internos', 'sigla' => 'SII', 'tipo' => 'servicio_publico', 'email' => 'consultas@sii.cl', 'sitio_web' => 'https://www.sii.cl'],
            ['nombre' => 'Dirección del Trabajo', 'sigla' => 'DT', 'tipo' => 'servicio_publico', 'email' => 'consultas@direcciondeltrabajo.cl', 'sitio_web' => 'https://www.dt.gob.cl'],
            ['nombre' => 'Superintendencia de Electricidad y Combustibles', 'sigla' => 'SEC', 'tipo' => 'superintendencia', 'sitio_web' => 'https://www.sec.cl'],
            ['nombre' => 'Ministerio de Vivienda y Urbanismo', 'sigla' => 'MINVU', 'tipo' => 'ministerio', 'sitio_web' => 'https://www.minvu.cl'],
            ['nombre' => 'Servicio Nacional del Consumidor', 'sigla' => 'SERNAC', 'tipo' => 'servicio_publico', 'sitio_web' => 'https://www.sernac.cl'],
            ['nombre' => 'Juzgado de Policía Local', 'sigla' => 'JPL', 'tipo' => 'tribunal'],
            ['nombre' => 'Municipalidad', 'sigla' => 'MUNI', 'tipo' => 'municipalidad'],
            ['nombre' => 'Contraloría General de la República', 'sigla' => 'CGR', 'tipo' => 'servicio_publico', 'sitio_web' => 'https://www.contraloria.cl'],
        ];

        foreach ($instituciones as $inst) {
            DB::table('instituciones')->insert(array_merge($inst, [
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// PLANTILLAS DE OFICIOS
// ========================================
class PlantillasOficioSeeder extends Seeder
{
    public function run(): void
    {
        $plantillas = [
            [
                'nombre' => 'Consulta a SII sobre distribución de ingresos',
                'codigo' => 'SII-DIST-001',
                'tipo' => 'consulta',
                'contenido' => 'Mediante la presente, la comunidad {{edificio_nombre}}, RUT {{edificio_rut}}, consulta a ese Servicio...',
            ],
            [
                'nombre' => 'Denuncia ruidos molestos',
                'codigo' => 'JPL-RUIDO-001',
                'tipo' => 'denuncia',
                'contenido' => 'Vengo en interponer denuncia por infracción al Reglamento de Copropiedad...',
            ],
            [
                'nombre' => 'Solicitud certificado de instalaciones',
                'codigo' => 'SEC-CERT-001',
                'tipo' => 'solicitud',
                'contenido' => 'Solicito a esa Superintendencia emitir certificado de cumplimiento...',
            ],
        ];

        foreach ($plantillas as $plantilla) {
            DB::table('plantillas_oficio')->insert(array_merge($plantilla, [
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}

// ========================================
// PLAN DE CUENTAS BASE
// ========================================
class PlanCuentasSeeder extends Seeder
{
    public function run(): void
    {
        // Este seeder se ejecutará por tenant
    }
}
