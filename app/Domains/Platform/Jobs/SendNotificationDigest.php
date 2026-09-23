<?php

declare(strict_types=1);

namespace App\Domains\Platform\Jobs;

use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Models\NotificationPreference;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * One email a day with everything that happened, for people who would rather not be told each time.
 */
final class SendNotificationDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $companyId)
    {
        $this->onQueue('mail');
    }

    public function handle(CurrentCompany $context): void
    {
        $company = Company::query()->find($this->companyId);
        if ($company === null) {
            return;
        }

        $context->runFor($company, function (): void {
            $wanted = NotificationPreference::query()->where('daily_digest', true)->pluck('user_id');

            User::query()->whereIn('id', $wanted)->where('is_active', true)->each(function (User $user): void {
                $since = now()->subDay();
                $items = $user->unreadNotifications()->where('created_at', '>=', $since)->latest()->limit(50)->get();
                if ($items->isEmpty()) {
                    return;
                }

                $lines = $items->map(static function ($notification): string {
                    /** @var array{title?: string, body?: string} $data */
                    $data = $notification->data;

                    return '<li><strong>'.e((string) ($data['title'] ?? 'Update')).'</strong><br>'.e((string) ($data['body'] ?? '')).'</li>';
                })->implode('');

                Mail::html(
                    '<p>Good morning '.e($user->name).',</p><p>Yesterday on '.e((string) config('branding.name')).':</p><ul>'.$lines.'</ul>'
                    .'<p><a href="'.e(route('inbox')).'">Open your inbox</a></p>',
                    fn ($message) => $message->to($user->email)->subject('Your daily summary: '.$items->count().' updates'),
                );
            });
        });
    }
}
