<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Settings for the whole instance. There is exactly one row, and only a Super Admin changes it.
 *
 * This is deliberately not company-owned: it applies across every company on the deployment.
 *
 * @property int $id
 * @property bool $two_factor_required
 * @property int|null $updated_by
 */
final class PlatformSetting extends Model
{
    protected $fillable = ['two_factor_required', 'updated_by'];

    /** The single settings row, created on first use so a fresh instance works without seeding. */
    public static function current(): self
    {
        return self::query()->firstOr(static fn (): self => self::query()->create(['two_factor_required' => false]));
    }

    /** Whether everyone signing in must have two-factor authentication set up. */
    public static function requiresTwoFactor(): bool
    {
        return self::current()->two_factor_required;
    }

    protected function casts(): array
    {
        return ['two_factor_required' => 'boolean'];
    }
}
