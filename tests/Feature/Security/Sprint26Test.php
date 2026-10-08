<?php

declare(strict_types=1);

use App\Actions\Fortify\PasswordValidationRules;
use App\Domains\Cms\Services\CmsException;
use App\Domains\Cms\Services\UploadGuard;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Models\PlatformSetting;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->marketing = userWithRole($this->company, Role::Marketing);
});

it('refuses SVG uploads, because browsers run them as code', function (): void {
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    expect(fn () => app(UploadGuard::class)->check($svg))->toThrow(CmsException::class, 'SVG files are not accepted');
});

it('re-encodes an uploaded image so only the picture survives', function (): void {
    // An image with something appended after the picture data, as a payload would be.
    $path = tempnam(sys_get_temp_dir(), 'test').'.jpg';
    $image = imagecreatetruecolor(40, 30);
    imagejpeg($image, $path);
    imagedestroy($image);
    file_put_contents($path, '<?php echo "payload"; ?>', FILE_APPEND);

    $cleaned = app(UploadGuard::class)->check(new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true));

    expect($cleaned)->not->toBeNull()
        ->and(file_get_contents((string) $cleaned))->not->toContain('payload')
        ->and(getimagesize((string) $cleaned))->not->toBeFalse();
});

it('asks everyone for two-factor authentication once a Super Admin requires it', function (): void {
    $this->marketing->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

    // Off by default, so a fresh instance is usable straight away.
    $this->actingAs($this->marketing->fresh())->get('/website/pages')->assertOk();

    PlatformSetting::current()->update(['two_factor_required' => true]);
    $this->actingAs($this->marketing->fresh())->get('/website/pages')->assertRedirect('/settings/profile');
});

it('insists on a long password that has not appeared in a breach', function (): void {
    $rules = (new class
    {
        use PasswordValidationRules;

        /** @return array<int, mixed> */
        public function get(): array
        {
            return $this->passwordRules();
        }
    })->get();

    $password = collect($rules)->first(fn (mixed $rule): bool => $rule instanceof Password);
    expect($password)->not->toBeNull();

    $validator = Validator::make(
        ['password' => 'short1', 'password_confirmation' => 'short1'],
        ['password' => $rules],
    );
    expect($validator->fails())->toBeTrue();
});
