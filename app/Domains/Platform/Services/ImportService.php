<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Loading opening data from a spreadsheet. Every row is checked first; nothing is written unless the
 * whole file is good, so a half-loaded import can never happen.
 */
final class ImportService
{
    private const int MAX_ROWS = 5000;

    /**
     * @return array{rows: int, valid: int, errors: list<array{row: int, message: string}>, preview: list<array<string, string>>}
     */
    public function check(string $type, string $contents, ?Project $project = null): array
    {
        $definition = $this->definition($type);
        $rows = $this->parse($contents, array_keys($definition['columns']));

        $errors = [];
        $valid = 0;
        foreach ($rows as $i => $row) {
            $message = $this->problemWith($definition, $row, $project);
            if ($message !== null) {
                $errors[] = ['row' => $i + 2, 'message' => $message];

                continue;
            }
            $valid++;
        }

        return ['rows' => count($rows), 'valid' => $valid, 'errors' => array_slice($errors, 0, 50), 'preview' => array_slice($rows, 0, 5)];
    }

    /**
     * Import a file. Any problem rolls the whole thing back.
     *
     * @return array{imported: int, errors: list<array{row: int, message: string}>}
     */
    public function import(string $type, string $contents, ?Project $project, User $by): array
    {
        $definition = $this->definition($type);
        $checked = $this->check($type, $contents, $project);
        if ($checked['errors'] !== []) {
            return ['imported' => 0, 'errors' => $checked['errors']];
        }

        $rows = $this->parse($contents, array_keys($definition['columns']));
        /** @var class-string<Model> $model */
        $model = $definition['model'];

        $imported = DB::transaction(function () use ($rows, $model, $definition, $project): int {
            $count = 0;
            foreach ($rows as $row) {
                $attributes = array_filter($row, static fn (string $value): bool => $value !== '');
                if (($definition['project'] ?? false) && $project !== null) {
                    $attributes['project_id'] = $project->id;
                }
                $model::query()->create($attributes);
                $count++;
            }

            return $count;
        });

        activity('imports')->causedBy($by)->withProperties(['type' => $type, 'rows' => $imported])->log('Opening data imported');

        return ['imported' => $imported, 'errors' => []];
    }

    /** The CSV template for a type, with the header row and one example line. */
    public function template(string $type): string
    {
        $definition = $this->definition($type);
        $headers = array_keys($definition['columns']);
        $labels = array_map(static fn (array $c): string => $c['label'], $definition['columns']);

        return implode(',', $headers)."\n".'# '.implode(',', $labels)."\n";
    }

    /**
     * @return array<string, array{label: string, model: class-string<Model>, unique: string, project?: bool, columns: array<string, array{label: string, required: bool}>}>
     */
    public function types(): array
    {
        /** @var array<string, array{label: string, model: class-string<Model>, unique: string, project?: bool, columns: array<string, array{label: string, required: bool}>}> $types */
        $types = (array) config('imports');

        return $types;
    }

    /**
     * @return array{label: string, model: class-string<Model>, unique: string, project?: bool, columns: array<string, array{label: string, required: bool}>}
     */
    private function definition(string $type): array
    {
        $types = $this->types();
        if (! isset($types[$type])) {
            throw new RuntimeException("There is no import for {$type}.");
        }

        return $types[$type];
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, string>>
     */
    private function parse(string $contents, array $columns): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        $header = null;
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                continue;
            }
            $values = str_getcsv($line, ',', '"', '\\');
            if ($header === null) {
                $header = array_map(static fn (?string $h): string => trim((string) $h), $values);

                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
            $row = [];
            foreach ($header as $i => $name) {
                if (in_array($name, $columns, true)) {
                    $row[$name] = trim((string) ($values[$i] ?? ''));
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array{unique: string, project?: bool, columns: array<string, array{label: string, required: bool}>, model: class-string<Model>}  $definition
     * @param  array<string, string>  $row
     */
    private function problemWith(array $definition, array $row, ?Project $project): ?string
    {
        foreach ($definition['columns'] as $name => $column) {
            if ($column['required'] && ($row[$name] ?? '') === '') {
                return "{$column['label']} is needed.";
            }
        }

        if (($definition['project'] ?? false) && $project === null) {
            return 'Choose the project this file belongs to.';
        }

        $key = $definition['unique'];
        if (($row[$key] ?? '') !== '') {
            /** @var class-string<Model> $model */
            $model = $definition['model'];
            $exists = $model::query()->where($key, $row[$key])
                ->when(($definition['project'] ?? false) && $project !== null, fn ($q) => $q->where('project_id', $project?->id))
                ->exists();
            if ($exists) {
                return "\"{$row[$key]}\" is already in the system.";
            }
        }

        $dates = array_filter(array_keys($definition['columns']), static fn (string $c): bool => str_ends_with($c, '_date') || str_ends_with($c, '_on'));
        foreach ($dates as $field) {
            if (($row[$field] ?? '') !== '' && Validator::make($row, [$field => 'date'])->fails()) {
                return "{$definition['columns'][$field]['label']} must be a date like 2027-03-15.";
            }
        }

        return null;
    }
}
