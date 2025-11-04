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
        $tz = 'Asia/Tokyo';

        /** =======================================
         *  User 2
         *  ======================================= */
        $user2 = User::find(2);
        if ($user2) {
            $declaration = RouteDeclaration::updateOrCreate(
                [
                    'user_id'        => $user2->id,
                    'effective_date' => '2025-04-01',
                ],
                [
                    'submitted_at'    => Carbon::now($tz),
                    'closest_station' => 'Tennoji',
                    'train_line'      => 'Midosuji',
                    'reason'          => 'New Academic Year',
                ]
            );

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
                        'trip_type'    => ExpenseTripType::ROUND_TRIP,
                        'amount'       => $r['amount'],
                        'route_text'   => 'Midosuji Line',
                        'note'         => null,
                    ]
                );
            }
            $this->command?->info('User 2 route declaration completed.');
        } else {
            $this->command?->warn('User ID 2 not found. Skipped.');
        }

        /** =======================================
         *  User 4
         *  ======================================= */
        $user4 = User::find(4);
        if ($user4) {
            $declaration = RouteDeclaration::updateOrCreate(
                [
                    'user_id'        => $user4->id,
                    'effective_date' => '2025-04-01',
                ],
                [
                    'submitted_at'    => Carbon::now($tz),
                    'closest_station' => 'Tennoji',
                    'train_line'      => 'Various Lines',
                    'reason'          => 'Multi-location schedule',
                ]
            );

            $rows = [
                ['dow' => 'Sun', 'from' => 'Tennoji',  'to' => 'Ikoma',     'amount' => 1680, 'trip_type' => ExpenseTripType::ROUND_TRIP],
                ['dow' => 'Wed', 'from' => 'Tennoji',  'to' => 'Ikoma',     'amount' => 1680, 'trip_type' => ExpenseTripType::ROUND_TRIP],
                ['dow' => 'Fri', 'from' => 'Tennoji',  'to' => 'Umeda',     'amount' => 580,  'trip_type' => ExpenseTripType::ROUND_TRIP],
                ['dow' => 'Sat', 'from' => 'Tennoji',  'to' => 'Fujitera',  'amount' => 380,  'trip_type' => ExpenseTripType::ONE_WAY],
                ['dow' => 'Sat', 'from' => 'Fujitera', 'to' => 'Yao',       'amount' => 280,  'trip_type' => ExpenseTripType::ONE_WAY],
                ['dow' => 'Sat', 'from' => 'Yao',      'to' => 'Tennoji',   'amount' => 280,  'trip_type' => ExpenseTripType::ONE_WAY],
            ];

            foreach ($rows as $r) {
                RouteDetail::updateOrCreate(
                    [
                        'route_declaration_id' => $declaration->id,
                        'dow'                  => $r['dow'],
                        'from_station'         => $r['from'],
                        'to_station'           => $r['to'],
                    ],
                    [
                        'trip_type'  => $r['trip_type'],
                        'amount'     => $r['amount'],
                        'route_text' => null,
                        'note'       => null,
                    ]
                );
            }
            $this->command?->info('User 4 route declaration completed.');
        } else {
            $this->command?->warn('User ID 4 not found. Skipped.');
        }
    }
}
