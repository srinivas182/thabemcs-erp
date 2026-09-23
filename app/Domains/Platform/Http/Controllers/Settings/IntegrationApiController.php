<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers\Settings;

use App\Domains\Platform\Models\Webhook;
use App\Domains\Platform\Models\WebhookDelivery;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API access for other systems: read-only tokens and webhooks.
 */
final class IntegrationApiController
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-integrations');
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/api', [
            'tokens' => PersonalAccessToken::query()->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)->orderByDesc('created_at')->get()
                ->map(static fn (PersonalAccessToken $t): array => [
                    'id' => $t->id, 'name' => $t->name, 'created' => $t->created_at?->toIso8601String(), 'lastUsed' => $t->last_used_at?->toIso8601String(),
                ]),
            'webhooks' => Webhook::query()->orderBy('name')->get()->map(static fn (Webhook $w): array => [
                'id' => $w->ulid, 'name' => $w->name, 'url' => $w->url, 'events' => $w->events, 'active' => $w->active,
                'lastDelivered' => $w->last_delivered_at?->toIso8601String(), 'lastError' => $w->last_error, 'failures' => $w->failures,
            ]),
            'deliveries' => WebhookDelivery::query()->with('webhook:id,name')->latest('id')->limit(20)->get()
                ->map(static fn (WebhookDelivery $d): array => [
                    'id' => $d->id, 'webhook' => $d->webhook->name, 'event' => $d->event, 'status' => $d->response_status,
                    'error' => $d->error, 'attempts' => $d->attempts, 'delivered' => $d->delivered_at?->toIso8601String(), 'at' => $d->created_at->toIso8601String(),
                ]),
            'events' => collect((array) config('webhooks.events'))->map(static fn (string $l, string $k): array => ['key' => $k, 'label' => $l])->values(),
        ]);
    }

    public function createToken(Request $request): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        /** @var User $user */
        $user = $request->user();
        // Read-only: tokens can fetch data through the API, never change anything.
        $token = $user->createToken((string) $data['name'], ['read']);

        return back()->with('success', 'Token created. Copy it now, it is not shown again: '.$token->plainTextToken);
    }

    public function revokeToken(Request $request, int $token): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->whereKey($token)->delete();

        return back()->with('success', 'Token revoked.');
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'url' => ['required', 'url:https', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(array_keys((array) config('webhooks.events')))],
        ]);
        /** @var User $user */
        $user = $request->user();
        $secret = Str::random(40);
        Webhook::query()->create([...$data, 'secret' => $secret, 'active' => true, 'created_by' => $user->id]);

        return back()->with('success', "Webhook added. Its signing secret is {$secret} — copy it now, it is not shown again.");
    }

    public function updateWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $webhook->update(['active' => (bool) $data['active'], 'failures' => 0, 'last_error' => null]);

        return back()->with('success', $data['active'] ? 'Webhook switched on.' : 'Webhook switched off.');
    }

    public function destroyWebhook(Webhook $webhook): RedirectResponse
    {
        Gate::authorize('manage-integrations');
        $webhook->delete();

        return back()->with('success', 'Webhook removed.');
    }
}
