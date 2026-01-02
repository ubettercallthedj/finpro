<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Edificio;
use App\Models\Unidad;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // Sembrar datos de prueba
    }

    /** @test */
    public function test_health_check_endpoint()
    {
        $response = $this->getJson('/api/health');
        
        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'ok',
                     'app' => 'DATAPOLIS PRO',
                 ]);
    }

    /** @test */
    public function test_login_con_credenciales_validas()
    {
        $user = User::factory()->create([
            'email' => 'test@datapolis.cl',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@datapolis.cl',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'user' => ['id', 'name', 'email'],
                     'token'
                 ]);
    }

    /** @test */
    public function test_login_con_credenciales_invalidas()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'noexiste@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function test_usuario_puede_ver_edificios()
    {
        $user = User::factory()->create();
        
        Edificio::factory()->count(3)->create([
            'tenant_id' => $user->tenant_id
        ]);

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/edificios');

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    /** @test */
    public function test_crear_edificio_requiere_autenticacion()
    {
        $response = $this->postJson('/api/edificios', [
            'nombre' => 'Edificio Test',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function test_crear_edificio_con_datos_validos()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/edificios', [
                             'nombre' => 'Torre Providencia',
                             'direccion' => 'Providencia 1234',
                             'comuna' => 'Providencia',
                             'rut' => '12345678-9',
                             'tipo' => 'edificio',
                             'total_unidades' => 50,
                         ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'id']);

        $this->assertDatabaseHas('edificios', [
            'nombre' => 'Torre Providencia',
            'tenant_id' => $user->tenant_id,
        ]);
    }

    /** @test */
    public function test_validacion_al_crear_edificio()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
                         ->postJson('/api/edificios', [
                             'nombre' => '', // Falta nombre
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['nombre', 'direccion', 'comuna', 'rut']);
    }

    /** @test */
    public function test_usuario_puede_ver_unidades_de_su_tenant()
    {
        $user = User::factory()->create();
        $edificio = Edificio::factory()->create(['tenant_id' => $user->tenant_id]);
        
        Unidad::factory()->count(5)->create([
            'tenant_id' => $user->tenant_id,
            'edificio_id' => $edificio->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/unidades');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function test_dashboard_stats_requiere_autenticacion()
    {
        $response = $this->getJson('/api/dashboard/stats');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_dashboard_stats_retorna_estadisticas()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
                         ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'total_unidades',
                     'recaudacion_mes',
                     'morosidad_total',
                     'contratos_activos',
                 ]);
    }
}
