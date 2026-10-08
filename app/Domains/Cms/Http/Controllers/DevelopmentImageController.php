<?php

declare(strict_types=1);

namespace App\Domains\Cms\Http\Controllers;

use App\Domains\Cms\Models\CmsMedia;
use App\Domains\Projects\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Chooses which photograph the public website shows for each development. Everything else about a
 * development comes from the project itself, so this screen only deals with the picture.
 */
final class DevelopmentImageController
{
    public function index(): Response
    {
        Gate::authorize('manage-content');

        $media = CmsMedia::query()->where('mime_type', 'like', 'image/%')->latest('id')->limit(80)->get()
            ->map(static fn (CmsMedia $m): array => ['id' => $m->ulid, 'url' => $m->url(), 'name' => $m->file_name, 'alt' => $m->alt])
            ->values();

        $developments = Project::query()->whereIn('status', ['active', 'completed'])->orderBy('name')->get()
            ->map(static function (Project $project): array {
                $chosen = $project->website_media_id === null
                    ? null
                    : CmsMedia::query()->find($project->website_media_id);

                return [
                    'id' => $project->ulid,
                    'name' => $project->name,
                    'town' => $project->town,
                    'stage' => $project->stage->label(),
                    'image' => $chosen?->url(),
                    'imageId' => $chosen?->ulid,
                ];
            })->values();

        return Inertia::render('cms/developments', ['developments' => $developments, 'media' => $media]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-content');
        $data = $request->validate(['media' => ['nullable', 'string']]);

        $media = ($data['media'] ?? null) === null
            ? null
            : CmsMedia::query()->where('ulid', $data['media'])->firstOrFail();

        $project->update(['website_media_id' => $media?->id]);

        return back()->with('success', $media === null
            ? 'Photograph removed. The website will show the illustration again.'
            : 'Photograph set. It appears on the website straight away.');
    }
}
