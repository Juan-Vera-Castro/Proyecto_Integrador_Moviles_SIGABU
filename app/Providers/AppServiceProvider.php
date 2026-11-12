<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\CasoInclusionPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        // Permisos de los formatos de Inclusión. Son habilidades con nombre porque Caso también
        // pertenece a otros servicios, con sus propias reglas.
        foreach (['gestionar', 'verConsentimiento', 'diligenciarConsentimiento', 'consultarRecursos', 'reabrir'] as $accion) {
            Gate::define("inclusion.{$accion}", [CasoInclusionPolicy::class, $accion]);
        }

        // Los avisos guardan "usuario" y no el nombre de la clase: renombrar el modelo no rompe
        // los avisos ya guardados.
        Relation::morphMap(['usuario' => User::class]);

        // Avisos sin leer de la campana, una sola consulta por página. Si la tabla aún no existe
        // (migración pendiente en el servidor), la página se muestra igual, sin el contador.
        View::composer('layouts.app', function ($view) {
            $usuario = Auth::user();

            $view->with('avisosSinLeer', $usuario
                ? (int) rescue(fn () => $usuario->unreadNotifications()->count(), 0)
                : 0);
        });

        // Fuera de producción, avisa en el log de cada consulta N+1 (una relación cargada dentro de
        // un listado sin with()). Cada consulta extra es un viaje a la base de datos.
        if (! $this->app->isProduction()) {
            Model::preventLazyLoading();
            Model::handleLazyLoadingViolationUsing(
                fn (Model $model, string $relation) => Log::warning('Consulta N+1: '.$model::class.'::'.$relation)
            );
        }
    }
}
