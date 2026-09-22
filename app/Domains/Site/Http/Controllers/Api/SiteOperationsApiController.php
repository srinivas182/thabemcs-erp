<?php

declare(strict_types=1);

namespace App\Domains\Site\Http\Controllers\Api;

use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Procurement\Exceptions\ProcurementException;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderLine;
use App\Domains\Procurement\Services\ProcurementService;
use App\Domains\Projects\Models\Project;
use App\Domains\Site\Models\Inspection;
use App\Domains\Site\Models\SiteInstruction;
use App\Domains\Site\Models\Snag;
use App\Domains\Site\Services\SiteCaptureService;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Workforce\Models\CrewAttendance;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\EmployeeAllocation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Site app endpoints added in Sprint 13: crew attendance, goods received against orders,
 * snags, inspections and site instructions. All creates are idempotent by clientId.
 */
final class SiteOperationsApiController
{
    public function __construct(private readonly SiteCaptureService $capture, private readonly ProcurementService $procurement) {}

    /** Workers currently allocated to the project, for the crew register. */
    public function employees(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $today = Carbon::today()->toDateString();
        $ids = EmployeeAllocation::query()->where('project_id', $project->id)->whereDate('from_date', '<=', $today)
            ->where(fn ($q) => $q->whereNull('to_date')->orWhereDate('to_date', '>=', $today))->pluck('employee_id');

        return response()->json(['data' => Employee::query()->whereIn('id', $ids)->where('status', 'active')->orderBy('last_name')->get()
            ->map(static fn (Employee $e): array => ['id' => $e->ulid, 'number' => $e->employee_number, 'name' => $e->name(), 'jobTitle' => $e->job_title])]);
    }

    public function crewAttendance(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.clientId' => ['required', 'uuid'],
            'entries.*.employeeId' => ['required', 'string'],
            'entries.*.status' => ['required', 'in:present,absent,sick,leave'],
            'entries.*.timeIn' => ['nullable', 'date_format:H:i'],
            'entries.*.timeOut' => ['nullable', 'date_format:H:i'],
        ]);
        $user = $this->user($request);
        $employees = Employee::query()->whereIn('ulid', array_column($data['entries'], 'employeeId'))->pluck('id', 'ulid');

        $saved = 0;
        DB::transaction(function () use ($data, $employees, $project, $user, &$saved): void {
            foreach ($data['entries'] as $entry) {
                $employeeId = $employees[$entry['employeeId']] ?? null;
                if ($employeeId === null) {
                    continue;
                }
                // One record per worker per day: a later register for the same day updates it.
                $row = CrewAttendance::query()->where('employee_id', $employeeId)->whereDate('worked_on', (string) $data['date'])->first()
                    ?? new CrewAttendance(['employee_id' => $employeeId, 'worked_on' => $data['date'], 'client_id' => $entry['clientId']]);
                $row->fill([
                    'project_id' => $project->id, 'status' => $entry['status'], 'time_in' => $entry['timeIn'] ?? null,
                    'time_out' => $entry['timeOut'] ?? null, 'recorded_by' => $user->id,
                ])->save();
                $saved++;
            }
        });

        return response()->json(['data' => ['saved' => $saved]], 201);
    }

    /** Issued orders for the project with what is still to be delivered, cached on the phone for receiving offline. */
    public function orders(Request $request): JsonResponse
    {
        $project = $this->project($request);

        return response()->json(['data' => PurchaseOrder::query()->with(['supplier:id,name', 'lines'])->where('project_id', $project->id)
            ->whereIn('status', ['issued', 'partially_received'])->orderBy('number')->get()
            ->map(static fn (PurchaseOrder $o): array => [
                'id' => $o->ulid, 'reference' => $o->reference(), 'supplier' => $o->supplier->name,
                'lines' => $o->lines->map(static fn (PurchaseOrderLine $l): array => [
                    'id' => $l->id, 'description' => $l->description, 'unit' => $l->unit, 'ordered' => (float) $l->quantity, 'outstanding' => $l->outstanding(),
                ])->values(),
            ])]);
    }

    public function receive(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'], 'orderId' => ['required', 'string'], 'receivedOn' => ['required', 'date', 'before_or_equal:today'],
            'quantities' => ['required', 'json'], 'notes' => ['nullable', 'string', 'max:2000'], 'photo' => ['nullable', 'image', 'max:10240'],
        ]);

        if ($existing = GoodsReceipt::query()->where('client_id', $data['clientId'])->first()) {
            return response()->json(['data' => ['id' => $existing->ulid]]);
        }

        $order = PurchaseOrder::query()->where('ulid', $data['orderId'])->where('project_id', $project->id)->firstOrFail();
        /** @var array<string|int, float|int|string> $raw */
        $raw = json_decode((string) $data['quantities'], true) ?: [];
        $quantities = [];
        foreach ($raw as $lineId => $qty) {
            $quantities[(int) $lineId] = (float) $qty;
        }

        try {
            $receipt = $this->procurement->receive($order, $quantities, (string) $data['receivedOn'], null, $data['notes'] ?? null, $this->user($request));
        } catch (ProcurementException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['quantities' => [$e->getMessage()]]], 422);
        }

        $photo = $request->file('photo');
        $path = null;
        if ($photo !== null) {
            try {
                [$sitePhoto] = $this->capture->photo($project, [
                    'client_id' => (string) Str::uuid(), 'captured_at' => now()->toIso8601String(), 'caption' => "Delivery note for {$order->reference()}",
                ], $photo, $this->user($request), $receipt);
                $path = $sitePhoto->path;
            } catch (QuotaExceededException) {
                // The receipt stands without the photo.
            }
        }
        $receipt->forceFill(['client_id' => $data['clientId'], 'photo_path' => $path])->save();

        return response()->json(['data' => ['id' => $receipt->ulid, 'number' => sprintf('GRN-%04d', $receipt->number)]], 201);
    }

    public function snag(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'], 'description' => ['required', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:200'],
            'supplierId' => ['nullable', 'string'], 'dueOn' => ['nullable', 'date'], 'photo' => ['nullable', 'image', 'max:10240'],
        ]);
        if ($existing = Snag::query()->where('client_id', $data['clientId'])->first()) {
            return response()->json(['data' => ['id' => $existing->ulid]]);
        }

        $snag = Snag::query()->create([
            'client_id' => $data['clientId'], 'project_id' => $project->id, 'description' => $data['description'], 'location' => $data['location'] ?? null,
            'supplier_id' => isset($data['supplierId']) ? Supplier::query()->where('ulid', $data['supplierId'])->value('id') : null,
            'due_on' => $data['dueOn'] ?? null,
        ]);
        if ($request->file('photo') !== null) {
            try {
                $this->capture->photo($project, ['client_id' => (string) Str::uuid(), 'captured_at' => now()->toIso8601String(), 'caption' => "Snag: {$snag->description}"], $request->file('photo'), $this->user($request), $snag);
            } catch (QuotaExceededException) {
            }
        }

        return response()->json(['data' => ['id' => $snag->ulid]], 201);
    }

    public function inspection(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'clientId' => ['required', 'uuid'], 'kind' => ['required', 'in:quality,safety'], 'title' => ['required', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'], 'result' => ['required', 'in:pass,fail,partial'],
            'findings' => ['nullable', 'required_unless:result,pass', 'string', 'max:5000'], 'inspectedOn' => ['required', 'date', 'before_or_equal:today'],
        ]);
        if ($existing = Inspection::query()->where('client_id', $data['clientId'])->first()) {
            return response()->json(['data' => ['id' => $existing->ulid]]);
        }

        $inspection = Inspection::query()->create([
            'client_id' => $data['clientId'], 'project_id' => $project->id, 'kind' => $data['kind'], 'title' => $data['title'], 'location' => $data['location'] ?? null,
            'result' => $data['result'], 'findings' => $data['findings'] ?? null, 'inspected_on' => $data['inspectedOn'], 'inspected_by' => $this->user($request)->id,
        ]);

        return response()->json(['data' => ['id' => $inspection->ulid]], 201);
    }

    public function instruction(Request $request): JsonResponse
    {
        $project = $this->project($request);
        Gate::authorize('manage-site');
        $data = $request->validate([
            'clientId' => ['required', 'uuid'], 'supplierId' => ['nullable', 'string'], 'subject' => ['required', 'string', 'max:200'],
            'instruction' => ['required', 'string', 'max:5000'], 'costImplication' => ['boolean'], 'timeImplication' => ['boolean'],
        ]);
        if ($existing = SiteInstruction::query()->where('client_id', $data['clientId'])->first()) {
            return response()->json(['data' => ['id' => $existing->ulid, 'number' => $existing->number]]);
        }

        $instruction = DB::transaction(fn () => SiteInstruction::query()->create([
            'client_id' => $data['clientId'], 'project_id' => $project->id,
            'number' => (int) SiteInstruction::query()->where('project_id', $project->id)->lockForUpdate()->max('number') + 1,
            'supplier_id' => isset($data['supplierId']) ? Supplier::query()->where('ulid', $data['supplierId'])->value('id') : null,
            'subject' => $data['subject'], 'instruction' => $data['instruction'],
            'cost_implication' => (bool) ($data['costImplication'] ?? false), 'time_implication' => (bool) ($data['timeImplication'] ?? false),
            'issued_by' => $this->user($request)->id, 'issued_at' => now(),
        ]));

        return response()->json(['data' => ['id' => $instruction->ulid, 'number' => $instruction->number]], 201);
    }

    private function project(Request $request): Project
    {
        Gate::authorize('capture-site');
        $request->validate(['projectId' => ['required', 'string']]);

        return Project::query()->where('ulid', $request->string('projectId')->toString())->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
