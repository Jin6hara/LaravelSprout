<?php

namespace Database\Factories;

use App\Models\CommuterPass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommuterPassFactory extends Factory
{
    protected $model = CommuterPass::class;

    public function definition(): array
    {
        $user = User::inRandomOrder()->first() ?? User::factory()->create();
        $from = now()->startOfMonth()->subMonths(1);
        $to   = now()->endOfMonth()->addMonths(2);

        return [
            'user_id'      => $user->id,
            'date_from'    => $from->toDateString(),
            'date_to'      => $to->toDateString(),
            'station_from' => '江坂',
            'station_to'   => '本町',
            'note'         => null,
            'cost'         => 12000,
        ];
    }
}
