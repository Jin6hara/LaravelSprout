<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\User;
use App\Models\RouteDeclaration;
use App\Models\RouteDetail;
use App\Enums\ExpenseTripType;

class RouteDeclarationUser2Seeder extends Seeder
{
    public function run(): void
    {
        $user = User::find(2);
        if (!$user) {
            $this->command?->warn('User ID 2 not found. Skipped.');
            return;
        }

        $tz = 'Asia/Tokyo';

        // Header: route_declarations
        $declaration = RouteDeclaration::updateOrCreate(
            [
                'user_id'        => $user->id,
                'effective_date' => '2025-04-01',
            ],
            [
                'submitted_at'   => Carbon::now($tz),
                'closest_station' => 'Tennoji',      // normalize
                'train_line'     => 'Midosuji',
                'reason'         => 'New Academic Year',
            ]
        );

        // Details: route_details (Wed–Sun)
        $rows = [
            ['dow' => 'Wed', 'from' => 'Tennoji', 'to' => 'Umeda', 'amount' => 580],
            ['dow' => 'Thu', 'from' => 'Tennoji', 'to' => 'Umeda', 'amount' => 580],
            ['dow' => 'Fri', 'from' => 'Tennoji', 'to' => 'Umeda', 'amount' => 580],
            ['dow' => 'Sat', 'from' => 'Tennoji', 'to' => 'Umeda', 'amount' => 580],
            ['dow' => 'Sun', 'from' => 'Tennoji', 'to' => 'Umeda', 'amount' => 580],
        ];

        foreach ($rows as $r) {
            RouteDetail::updateOrCreate(
                [
                    'route_declaration_id' => $declaration->id,
                    'dow'                  => $r['dow'],
                ],
                [
                    'from_station' => $r['from'],
                    'to_station'   => $r['to'],
                    'trip_type'    => ExpenseTripType::ROUND_TRIP, // 既存Enumを使用
                    'amount'       => $r['amount'],
                    'route_text'   => 'Midosuji Line',
                    'note'         => null,
                ]
            );
        }

        $this->command?->info('RouteDeclarationUser2Seeder completed.');
    }
}
