<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolProfile;
use App\Support\SchoolName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ✅ fixed image paths
        $profileMapImage = '/image/Map.png';
        $stationGuideImage = '/image/Transit_Logo.png';

        $schools = [
            [
                'name' => 'Umeda GB',
                'address' => 'Kita-ku, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Osaka-Umeda', 'line' => 'Hankyu Lines', 'walk_minutes' => 3, 'guide_text' => 'Direct access via Hankyu Grand Bldg concourse.'],
                    ['station_name' => 'Umeda', 'line' => 'Osaka Metro Midosuji Line', 'walk_minutes' => 6, 'guide_text' => 'North ticket gate → Hankyu area → Grand Bldg.'],
                ],
            ],
            [
                'name' => 'Namba',
                'address' => 'Chuo-ku, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Namba', 'line' => 'Osaka Metro Midosuji Line', 'walk_minutes' => 3, 'guide_text' => 'South ticket gate → underground mall → exit to street level.'],
                    ['station_name' => 'Osaka-Namba', 'line' => 'Kintetsu / Hanshin Namba Line', 'walk_minutes' => 6, 'guide_text' => 'East gate → walk toward Midosuji line connection.'],
                ],
            ],
            [
                'name' => 'Tennoji MP',
                'address' => 'Tennoji-ku, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Tennoji', 'line' => 'JR Lines', 'walk_minutes' => 2, 'guide_text' => 'Central gate → MIO Plaza passage (direct).'],
                    ['station_name' => 'Tennoji', 'line' => 'Osaka Metro Midosuji / Tanimachi', 'walk_minutes' => 4, 'guide_text' => 'West gate → JR connection passage.'],
                ],
            ],
            [
                'name' => 'Shijo',
                'address' => 'Shimogyo-ku, Kyoto, Japan',
                'stations' => [
                    ['station_name' => 'Shijo', 'line' => 'Kyoto Subway Karasuma Line', 'walk_minutes' => 2, 'guide_text' => 'North gate → Exit 23, building above.'],
                    ['station_name' => 'Karasuma', 'line' => 'Hankyu Kyoto Line', 'walk_minutes' => 3, 'guide_text' => 'East gate → underground link to Shijo.'],
                ],
            ],
            [
                'name' => 'Sannomiya',
                'address' => 'Chuo-ku, Kobe, Japan',
                'stations' => [
                    ['station_name' => 'Sannomiya', 'line' => 'JR Kobe Line', 'walk_minutes' => 4, 'guide_text' => 'Central exit → Flower Road south.'],
                    ['station_name' => 'Kobe-Sannomiya', 'line' => 'Hankyu / Hanshin', 'walk_minutes' => 5, 'guide_text' => 'East gate → walk along Sankita Street.'],
                ],
            ],
            [
                'name' => 'Nishinomiya',
                'address' => 'Nishinomiya, Hyogo, Japan',
                'stations' => [
                    ['station_name' => 'Nishinomiya-Kitaguchi', 'line' => 'Hankyu Kobe / Imazu Lines', 'walk_minutes' => 2, 'guide_text' => 'Northwest exit → deck to Gardens area.'],
                ],
            ],
            [
                'name' => 'Kyoto Ekimae',
                'address' => 'Shimogyo-ku, Kyoto, Japan',
                'stations' => [
                    ['station_name' => 'Kyoto', 'line' => 'JR Lines', 'walk_minutes' => 3, 'guide_text' => 'Central gate → Karasuma side → head to Yodobashi area.'],
                    ['station_name' => 'Kyoto', 'line' => 'Kintetsu / Subway Karasuma', 'walk_minutes' => 5, 'guide_text' => 'From Kintetsu gates follow connection to JR central gate.'],
                ],
            ],
            [
                'name' => 'Hirakata',
                'address' => 'Hirakata, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Hirakata-shi', 'line' => 'Keihan Main Line', 'walk_minutes' => 2, 'guide_text' => 'Central gate → T-SITE deck connection.'],
                ],
            ],
            [
                'name' => 'Takatsuki Kids',
                'address' => 'Takatsuki, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Takatsuki-shi', 'line' => 'Hankyu Kyoto Line', 'walk_minutes' => 4, 'guide_text' => 'North gate → walk toward Minage area.'],
                    ['station_name' => 'Takatsuki', 'line' => 'JR Kyoto Line', 'walk_minutes' => 8, 'guide_text' => 'South exit → Keyaki-dori eastbound.'],
                ],
            ],
            [
                'name' => 'Senri-Chuo',
                'address' => 'Toyonaka, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Senri-Chuo', 'line' => 'Kita-Osaka Kyuko / Osaka Monorail', 'walk_minutes' => 3, 'guide_text' => 'North concourse → S. entrance of shopping mall.'],
                ],
            ],
            [
                'name' => 'Kita-Senri',
                'address' => 'Suita, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Kita-Senri', 'line' => 'Hankyu Senri Line', 'walk_minutes' => 2, 'guide_text' => 'East exit → cross the plaza to the building.'],
                ],
            ],
            [
                'name' => 'Ibaraki',
                'address' => 'Ibaraki, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Ibaraki', 'line' => 'JR Kyoto Line', 'walk_minutes' => 5, 'guide_text' => 'Central exit → proceed south along station road.'],
                ],
            ],
            [
                'name' => 'Esaka',
                'address' => 'Suita, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Esaka', 'line' => 'Osaka Metro Midosuji Line', 'walk_minutes' => 3, 'guide_text' => 'South ticket gate → Exit 7 → office tower.'],
                ],
            ],
            [
                'name' => 'Kuzuha',
                'address' => 'Hirakata, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Kuzuha', 'line' => 'Keihan Main Line', 'walk_minutes' => 2, 'guide_text' => 'From the gate, follow signs to Kuzuha Mall.'],
                ],
            ],
            [
                'name' => 'Nagao',
                'address' => 'Hirakata, Osaka, Japan',
                'stations' => [
                    ['station_name' => 'Nagao', 'line' => 'JR Gakkentoshi Line', 'walk_minutes' => 4, 'guide_text' => 'West exit → straight along shopping street.'],
                ],
            ],
        ];

        // helper to make unique random 3-digit codes
        $used = [];
        $makeCode = function () use (&$used): string {
            do {
                $code = str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
            } while (in_array($code, $used, true));
            $used[] = $code;
            return $code;
        };

        DB::transaction(function () use ($schools, $now, $makeCode, $profileMapImage, $stationGuideImage) {
            foreach ($schools as $s) {
                $schoolName = SchoolName::normalize($s['name']);
                $school = School::query()
                    ->whereEqualsInsensitive('school_name', $schoolName)
                    ->first();

                if (!$school) {
                    $school = School::create([
                        'school_name' => $schoolName,
                        'school_code' => $makeCode(),
                        'name_kana'   => null,
                        'aliases'     => null,
                        'is_active'   => true,
                    ]);
                }

                // ✅ map_image_path fixed
                $profile = SchoolProfile::create([
                    'school_id'      => $school->id,
                    'address'        => $s['address'],
                    'description'    => null,
                    'map_image_path' => $profileMapImage,
                    'valid_from'     => $now,
                    'valid_to'       => null,
                ]);

                foreach ($s['stations'] as $i => $st) {
                    $profile->stations()->create([
                        'station_name'     => $st['station_name'],
                        'line'             => $st['line'] ?? null,
                        'walk_minutes'     => $st['walk_minutes'] ?? null,
                        'guide_text'       => $st['guide_text'] ?? null,
                        // ✅ all stations use Transit_logo.png
                        'guide_image_path' => $stationGuideImage,
                        'sort_order'       => $i,
                    ]);
                }
            }
        });
    }
}
