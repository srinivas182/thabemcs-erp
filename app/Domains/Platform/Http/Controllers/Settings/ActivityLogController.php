<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers\Settings;

use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit trail: who did what, and when. Company Admins and Directors see their own company;
 * Super Admins in the platform view see everything.
 */
final class ActivityLogController
{
    public function index(Request $request, CurrentCompany $context): Response
    {
        Gate::authorize('view-audit-log');

        $query = Activity::query()->with('causer')->latest('id');

        if ($context->check()) {
            $query->where('company_id', $context->id());
        }

        $activities = $query->paginate(50)->through(static function (Activity $activity): array {
            $causer = $activity->causer;

            return [
                'id' => $activity->getKey(),
                'description' => $activity->description,
                'log' => $activity->log_name,
                'subject' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'by' => $causer instanceof User ? $causer->name : 'System',
                'at' => $activity->created_at?->toIso8601String(),
            ];
        });

        return Inertia::render('settings/activity', ['activities' => $activities]);
    }
}
