<?php

declare(strict_types=1);

namespace App\Domains\Land\Services;

use App\Domains\Land\Enums\CheckResult;
use App\Domains\Land\Enums\LandStatus;
use App\Domains\Land\Models\LandCheck;
use App\Domains\Land\Models\LandParcel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LandService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): LandParcel
    {
        return DB::transaction(function () use ($attributes): LandParcel {
            $parcel = LandParcel::query()->create($attributes);

            /** @var list<array{key: string, title: string, required: bool}> $checks */
            $checks = config('land_checks');
            foreach ($checks as $i => $check) {
                LandCheck::query()->create([
                    'land_parcel_id' => $parcel->id, 'key' => $check['key'], 'title' => $check['title'],
                    'is_required' => $check['required'], 'sort' => $i,
                ]);
            }

            return $parcel;
        });
    }

    public function recordCheck(LandCheck $check, CheckResult $result, ?string $notes, User $by): void
    {
        $check->forceFill([
            'result' => $result,
            'notes' => $notes,
            'checked_by' => $result === CheckResult::Pending ? null : $by->id,
            'checked_at' => $result === CheckResult::Pending ? null : now(),
        ])->save();
    }

    /**
     * Required checks that are still pending or show an issue.
     *
     * @return list<string>
     */
    public function outstanding(LandParcel $parcel): array
    {
        $titles = $parcel->checks()->where('is_required', true)
            ->whereIn('result', [CheckResult::Pending, CheckResult::Issue])
            ->pluck('title')->all();

        return array_values(array_map(strval(...), $titles));
    }

    /**
     * Move the parcel through the acquisition pipeline, recording key dates.
     *
     * @throws ValidationException
     */
    public function changeStatus(LandParcel $parcel, LandStatus $status): void
    {
        if (in_array($status, [LandStatus::OfferAccepted, LandStatus::Transferred], true) && $this->outstanding($parcel) !== []) {
            throw ValidationException::withMessages([
                'status' => 'Finish due diligence first: every required check must be clear or not applicable.',
            ]);
        }

        $dates = match ($status) {
            LandStatus::OfferMade => ['offer_date' => $parcel->offer_date ?? now()->toDateString()],
            LandStatus::OfferAccepted => ['acceptance_date' => $parcel->acceptance_date ?? now()->toDateString()],
            LandStatus::Transferred => ['transfer_date' => $parcel->transfer_date ?? now()->toDateString()],
            default => [],
        };

        $parcel->forceFill(['status' => $status, ...$dates])->save();
    }
}
