<?php

declare(strict_types=1);

namespace Modules\DataProtection\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        $web = module_path('DataProtection', 'routes/web.php');
        if (is_file($web)) {
            Route::middleware('web')->group($web);
        }

        $api = module_path('DataProtection', 'routes/api.php');
        if (is_file($api)) {
            Route::middleware('api')->prefix('api')->group($api);
        }
    }
}
