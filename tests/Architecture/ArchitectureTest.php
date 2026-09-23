<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

arch('every class declares strict types')
    ->expect(['App', 'Modules'])
    ->toUseStrictTypes();

arch('env() is only read inside config files')
    ->expect('env')
    ->not->toBeUsedIn(['App', 'Modules']);

arch('controllers do not query the database directly')
    ->expect('Modules\*\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB']);

arch('no debugging helpers are left in code')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

function sourceFiles(): Finder
{
    return Finder::create()
        ->in([base_path('app'), base_path('Modules'), base_path('config'), base_path('routes'), base_path('database'), base_path('bootstrap'), base_path('tests')])
        ->exclude(['cache'])
        ->name('*.php')
        ->files();
}

it('contains no comments or docblocks in PHP source', function (): void {
    $offenders = [];
    foreach (sourceFiles() as $file) {
        foreach (token_get_all((string) file_get_contents($file->getRealPath())) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $offenders[] = $file->getRelativePathname().':'.$token[2];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('never authorises by role name', function (): void {
    $offenders = [];
    foreach (sourceFiles()->in([base_path('app'), base_path('Modules')])->notPath('tests') as $file) {
        if (preg_match('/->(hasRole|hasAnyRole|hasAllRoles)\(/', (string) file_get_contents($file->getRealPath()))) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('resolves every Inertia page rendered by the server to a page component', function (): void {
    $missing = [];
    foreach (sourceFiles()->in([base_path('Modules'), base_path('routes'), base_path('app')]) as $file) {
        preg_match_all("/Inertia::render\('([^']+)'/", (string) file_get_contents($file->getRealPath()), $matches);
        foreach ($matches[1] as $component) {
            [$module, $rest] = array_pad(explode('/', $component, 2), 2, '');
            $candidates = [base_path("Modules/{$module}/resources/js/Pages/{$rest}.tsx"), resource_path("js/pages/{$component}.tsx")];
            if (! array_filter($candidates, 'is_file')) {
                $missing[] = $component;
            }
        }
    }

    expect($missing)->toBe([]);
});
