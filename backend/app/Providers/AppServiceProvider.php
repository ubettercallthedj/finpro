<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\Persona;
use App\Models\BoletaGC;
use App\Observers\PersonaObserver;
use App\Observers\BoletaObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix para MySQL/MariaDB con índices largos
        Schema::defaultStringLength(191);

        // Registrar Observers
        Persona::observe(PersonaObserver::class);
        BoletaGC::observe(BoletaObserver::class);

        // Configuración de timezone
        date_default_timezone_set('America/Santiago');

        // Macros útiles
        \Illuminate\Support\Collection::macro('recursive', function () {
            return $this->map(function ($value) {
                if (is_array($value) || is_object($value)) {
                    return collect($value)->recursive();
                }
                return $value;
            });
        });

        // Configurar paginación
        \Illuminate\Pagination\Paginator::defaultView('pagination::bootstrap-4');
    }
}
