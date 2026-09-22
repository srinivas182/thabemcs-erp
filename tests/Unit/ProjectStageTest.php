<?php

declare(strict_types=1);

use App\Domains\Projects\Enums\ProjectStage;

it('follows the Thabekhulu development cycle in order', function (): void {
    expect(array_map(fn (ProjectStage $s) => $s->value, ProjectStage::cases()))
        ->toBe(['plan', 'fund', 'land', 'approve', 'build', 'sell_rent', 'close']);
});

it('knows the next stage and ends at close-out', function (): void {
    expect(ProjectStage::Plan->next())->toBe(ProjectStage::Fund)
        ->and(ProjectStage::Build->next())->toBe(ProjectStage::SellRent)
        ->and(ProjectStage::Close->next())->toBeNull();
});
