<?php

declare(strict_types=1);

/*
| OPcache preloading: the framework and the application's own classes are compiled once when PHP-FPM
| starts, instead of on the first request that needs them. Set opcache.preload to this file.
| Skipped automatically when the application has not been built yet (e.g. in CI).
*/

$root = __DIR__;

if (! is_file($root.'/vendor/autoload.php')) {
    return;
}

require $root.'/vendor/autoload.php';

$skip = static fn (string $path): bool => str_contains($path, '/tests/')
    || str_contains($path, '/laravel/pint/')
    || str_contains($path, '/larastan/')
    || str_contains($path, '/pestphp/')
    || str_contains($path, 'Illuminate/Foundation/Console/')
    || str_ends_with($path, 'helpers.php');

foreach ([$root.'/vendor/laravel/framework/src/Illuminate', $root.'/app'] as $directory) {
    if (! is_dir($directory)) {
        continue;
    }
    /** @var iterable<SplFileInfo> $files */
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($files as $file) {
        $path = $file->getRealPath();
        if ($path === false || ! str_ends_with($path, '.php') || $skip($path)) {
            continue;
        }
        try {
            opcache_compile_file($path);
        } catch (Throwable) {
            // Files that cannot be compiled on their own (traits with unmet dependencies) are skipped.
        }
    }
}
