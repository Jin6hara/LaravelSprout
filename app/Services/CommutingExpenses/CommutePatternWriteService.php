<?php

namespace App\Services\CommutingExpenses;

use App\Enums\ExpenseTripType;
use App\Models\CommutePattern;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommutePatternWriteService
{
    public function save(User $target, array $data, ?CommutePattern $pattern = null): CommutePattern
    {
        return DB::transaction(function () use ($data, $target, $pattern) {
            $pattern ??= new CommutePattern;
            $pattern->user_id = $target->id;
            $pattern->fill([
                'submitted_at' => $pattern->submitted_at ?: now('Asia/Tokyo'),
                'closest_station' => $data['closest_station'],
                'train_line' => $data['train_line'] ?? null,
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'reason' => $data['reason'] ?? null,
            ]);
            $pattern->save();

            $rows = collect($data['rows'])->values();
            $incomingIds = $rows
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($incomingIds->isNotEmpty()) {
                $ownedCount = $pattern->legs()
                    ->whereIn('id', $incomingIds)
                    ->count();

                abort_if($ownedCount !== $incomingIds->count(), 403, 'Some commute pattern leg IDs do not belong to this pattern.');
            }

            $deleteQuery = $pattern->legs();
            if ($incomingIds->isNotEmpty()) {
                $deleteQuery->whereNotIn('id', $incomingIds);
            }
            $deleteQuery->delete();

            foreach ($rows as $index => $row) {
                $payload = [
                    'dow' => $row['dow'],
                    'seq' => (int) ($row['seq'] ?? (($index + 1) * 100)),
                    'station_from' => $row['station_from'] ?? null,
                    'station_to' => $row['station_to'] ?? null,
                    'note' => $row['note'] ?? null,
                    'cost' => (int) ($row['cost'] ?? 0),
                    'trip_type' => $row['trip_type'] ?? ExpenseTripType::ROUND_TRIP->value,
                ];

                if (! empty($row['id'])) {
                    $pattern->legs()->whereKey($row['id'])->firstOrFail()->update($payload);
                } else {
                    $pattern->legs()->create($payload);
                }
            }

            return $pattern->fresh(['legs']);
        });
    }
}
