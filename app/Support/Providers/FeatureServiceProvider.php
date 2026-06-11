<?php

namespace App\Support\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider para features em vertical slice.
 *
 * Providers concretos declaram o mapa contrato→implementação via a propriedade
 * nativa `$bindings` e carregam as rotas no boot() com loadFeatureRoutes().
 */
abstract class FeatureServiceProvider extends ServiceProvider
{
    /**
     * Carrega o arquivo de rotas de uma feature sob um grupo de middleware + prefixo.
     * Default cobre o caso comum (api/api); admin e páginas web passam o seu próprio.
     */
    protected function loadFeatureRoutes(
        string $path,
        array|string $middleware = 'api',
        ?string $prefix = 'api',
    ): void {
        Route::middleware($middleware)
            ->prefix($prefix)
            ->group($path);
    }
}
