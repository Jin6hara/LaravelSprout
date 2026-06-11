<?php

/**
 * 定期券情報の新規作成・更新を担うサービス。
 */
namespace App\Services\CommutingExpenses;

use App\Models\CommuterPass;
use App\Models\User;

class CommuterPassWriteService
{
    public function create(User $target, array $data): CommuterPass
    {
        return $target->commuterPasses()->create($this->payload($data));
    }

    public function update(CommuterPass $pass, array $data): CommuterPass
    {
        $pass->update($this->payload($data));

        return $pass;
    }

    private function payload(array $data): array
    {
        return [
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
            'station_from' => $data['station_from'],
            'station_to' => $data['station_to'],
            'note' => $data['note'] ?? null,
            'cost' => $data['cost'] ?? 0,
        ];
    }
}
