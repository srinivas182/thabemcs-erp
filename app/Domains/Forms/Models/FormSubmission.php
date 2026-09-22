<?php

declare(strict_types=1);

namespace App\Domains\Forms\Models;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int $form_template_id
 * @property int $template_version
 * @property string $client_id
 * @property list<array{id: string, label: string, type: string, required: bool, options?: list<string>}> $fields
 * @property array<string, mixed> $answers
 * @property bool|null $passed
 * @property Carbon $submitted_at
 * @property int $submitted_by
 * @property-read FormTemplate $template
 * @property-read User $submitter
 * @property-read Project $project
 */
class FormSubmission extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'form_template_id', 'template_version', 'client_id', 'fields', 'answers', 'passed', 'submitted_at', 'submitted_by'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'answers' => 'array', 'passed' => 'boolean', 'submitted_at' => 'datetime', 'template_version' => 'integer'];
    }

    /**
     * @return BelongsTo<FormTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
