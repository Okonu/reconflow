<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Nwidart\Modules\Support\ModuleServiceProvider;

abstract class ModuleProvider extends ModuleServiceProvider
{
    protected function registerViews(): void {}

    protected function registerTranslations(): void {}
}
