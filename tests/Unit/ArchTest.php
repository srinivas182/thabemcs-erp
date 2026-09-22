<?php

declare(strict_types=1);

arch('no debugging calls are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('domain code uses strict types')
    ->expect('App\Domains')
    ->toUseStrictTypes();

arch('domain enums are enums')
    ->expect('App\Domains\Platform\Enums')
    ->toBeEnums();
