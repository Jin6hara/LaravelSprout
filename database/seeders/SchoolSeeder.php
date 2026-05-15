<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SchoolSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $profileMapImage   = '/image/Map.png';
        $stationGuideImage = '/image/Transit_Logo.png';

        // HQ / Hyper Online / Online / Education / Personal で共用する駅情報
        $hqStations = [
            [
                'station_name' => 'Osaka Tenmangu Station',
                'line'         => 'JR Tozai Line',
                'walk_minutes' => 2,
                'guide_text'   => 'About 2 minutes on foot from Exit 8.',
            ],
            [
                'station_name' => 'Minami-morimachi Station',
                'line'         => 'Osaka Metro Tanimachi Line / Sakaisuji Line',
                'walk_minutes' => 4,
                'guide_text'   => 'About 4 minutes on foot via Exit 4-A and JR Exit 8.',
            ],
            [
                'station_name' => 'Temmabashi Station',
                'line'         => 'Keihan Railway / Osaka Metro Tanimachi Line',
                'walk_minutes' => 12,
                'guide_text'   => 'Walk north-east toward Higashi-Tenma area.',
            ],
        ];

        $schools = [
            [
                'school_code' => '180',
                'school_name' => 'あびこ校',
                'name_kana' => null,
                'aliases' => ['Abiko', 'ECCあびこ校'],
                'profile' => [
                    'address' => 'Taisei Building 4F, 3-1-1 Abiko Higashi, Sumiyoshi-ku, Osaka-shi, Osaka 558-0013',
                    'description' => 'Right next to Exit 4 of Abiko Station on the Osaka Metro Midosuji Line.',
                    'stations' => [
                        ['station_name' => 'Abiko Station', 'line' => 'Osaka Metro Midosuji Line', 'walk_minutes' => 1, 'guide_text' => 'Right next to Exit 4. Taisei Building 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '132',
                'school_name' => '明石校',
                'name_kana' => null,
                'aliases' => ['Akashi', 'ECC明石校'],
                'profile' => [
                    'address' => 'Shiragiku Grand Building 4F, 1-7-4 Oakashicho, Akashi-shi, Hyogo 673-0891',
                    'description' => 'Near JR/Sanyo Akashi Station, just outside the west exit of Piole.',
                    'stations' => [
                        ['station_name' => 'Akashi Station', 'line' => 'JR Sanyo Main Line', 'walk_minutes' => 1, 'guide_text' => 'Near the south side of Akashi Station (JR). Shiragiku Grand Building 4F.'],
                        ['station_name' => 'Akashi Station', 'line' => 'Sanyo Electric Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the south side of Akashi Station (Sanyo). Shiragiku Grand Building 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '141',
                'school_name' => '淡路校',
                'name_kana' => null,
                'aliases' => ['Awaji', 'ECC淡路校'],
                'profile' => [
                    'address' => 'SWIFA Bldg. 4F, 4-18-10 Higashi Awaji, Higashiyodogawa-ku, Osaka-shi, Osaka 533-0023',
                    'description' => 'Right outside the east ticket gate of Hankyu Awaji Station.',
                    'stations' => [
                        ['station_name' => 'Awaji Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Right outside the east ticket gate. SWIFA Bldg. 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '167',
                'school_name' => '藤井寺校',
                'name_kana' => null,
                'aliases' => ['Fujidera', 'ECC藤井寺校'],
                'profile' => [
                    'address' => 'Sorya Building 1F, 2-8-41 Oka, Fujiidera-shi, Osaka 583-0027',
                    'description' => 'Right near the north exit of Kintetsu Fujiidera Station.',
                    'stations' => [
                        ['station_name' => 'Fujiidera Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the north exit. Sorya Building 1F.'],
                    ],
                ],
            ],
            [
                'school_code' => '108',
                'school_name' => '布施校',
                'name_kana' => null,
                'aliases' => ['Fuse', 'ECC布施校'],
                'profile' => [
                    'address' => 'Vernoll Fuse 1F, 1-8-37 Chodo, Higashiosaka-shi, Osaka 577-0056',
                    'description' => 'Near the north exit of Kintetsu Fuse Station.',
                    'stations' => [
                        ['station_name' => 'Fuse Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the north exit. Vernoll Fuse 1F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1A5',
                'school_name' => '五位堂校',
                'name_kana' => null,
                'aliases' => ['Goido', 'ECC五位堂校'],
                'profile' => [
                    'address' => 'Kashiba Mokuzai Ichibankan 3F, 2315 Kawaraguchi, Kashiba-shi, Nara 639-0225',
                    'description' => 'In front of the north exit of Kintetsu Goido Station.',
                    'stations' => [
                        ['station_name' => 'Goido Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'In front of the north exit. Kashiba Mokuzai Ichibankan 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '127',
                'school_name' => '姫路校',
                'name_kana' => null,
                'aliases' => ['Himeji', 'ECC姫路校'],
                'profile' => [
                    'address' => 'Yagi Building 2F, 255 Ekimaecho, Himeji-shi, Hyogo 670-0927',
                    'description' => 'Near the east exit of JR/Sanyo Himeji Station.',
                    'stations' => [
                        ['station_name' => 'Himeji Station', 'line' => 'JR Sanyo Main Line', 'walk_minutes' => 1, 'guide_text' => 'Near the east exit (JR). Yagi Building 2F.'],
                        ['station_name' => 'Himeji Station', 'line' => 'Sanyo Electric Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the east exit (Sanyo). Yagi Building 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '118',
                'school_name' => '枚方校',
                'name_kana' => null,
                'aliases' => ['Hirakata', 'ECC枚方校'],
                'profile' => [
                    'address' => 'Tom Sawyer Building 5F, 14-40 Okahigashicho, Hirakata-shi, Osaka 573-0032',
                    'description' => 'Near the south exit terminal of Keihan Hirakatashi Station.',
                    'stations' => [
                        ['station_name' => 'Hirakatashi Station', 'line' => 'Keihan Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the south exit terminal. Tom Sawyer Building 5F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1A3',
                'school_name' => 'オンラインハイパー',
                'name_kana' => null,
                'aliases' => ['Hyper Online'],
                'profile' => [
                    'address' => 'ECC Head Office Building 9F, 1-10-20 Higashi Tenma, Kita-ku, Osaka-shi, Osaka 530-0044',
                    'description' => 'Hyper Online office on the 9th floor of ECC Head Office.',
                    'stations' => $hqStations,
                ],
            ],
            [
                'school_code' => '112',
                'school_name' => '茨木校',
                'name_kana' => null,
                'aliases' => ['Ibaraki', 'ECC茨木校'],
                'profile' => [
                    'address' => 'Joyful Ibaraki 3F, 8-22 Futabacho, Ibaraki-shi, Osaka 567-0829',
                    'description' => 'Near the east exit of Hankyu Ibarakishi Station.',
                    'stations' => [
                        ['station_name' => 'Ibarakishi Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the east exit. Joyful Ibaraki 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '168',
                'school_name' => '生駒校',
                'name_kana' => null,
                'aliases' => ['Ikoma', 'ECC生駒校'],
                'profile' => [
                    'address' => 'Kintetsu Department Store 6F, 1600 Tanidacho, Ikoma-shi, Nara 630-0251',
                    'description' => 'Directly connected to the north exit of Kintetsu Ikoma Station.',
                    'stations' => [
                        ['station_name' => 'Ikoma Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Directly connected to the north exit. Kintetsu Department Store 6F.'],
                    ],
                ],
            ],
            [
                'school_code' => '117',
                'school_name' => '阪急伊丹校',
                'name_kana' => null,
                'aliases' => ['Itami', 'ECC阪急伊丹校'],
                'profile' => [
                    'address' => 'Reita 4F, Hankyu Itami Station Building, 1-1-1 Nishidai, Itami-shi, Hyogo 664-0858',
                    'description' => 'Inside Hankyu Itami Station Building, Reita 4F.',
                    'stations' => [
                        ['station_name' => 'Itami Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Inside Hankyu Itami Station Building, Reita 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1H6',
                'school_name' => '和泉中央校',
                'name_kana' => null,
                'aliases' => ['Izumi Chuo', 'ECC和泉中央校'],
                'profile' => [
                    'address' => 'Ecole Izumi North Building 3F, 4-5-2 Ibukino, Izumi-shi, Osaka 594-0041',
                    'description' => 'Near Izumi-Chuo Station.',
                    'stations' => [
                        ['station_name' => 'Izumi-Chuo Station', 'line' => 'Semboku Rapid Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the station. Ecole Izumi North Building 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1A7',
                'school_name' => 'JR堺校',
                'name_kana' => null,
                'aliases' => ['JR Sakai', 'ECCJR堺校'],
                'profile' => [
                    'address' => 'NK Building 3F, 1-1-11 Shinonome Nishimachi, Sakai-ku, Sakai-shi, Osaka 590-0013',
                    'description' => 'Directly connected to JR Sakaishi Station.',
                    'stations' => [
                        ['station_name' => 'Sakaishi Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'Directly connected to JR Sakaishi Station. NK Building 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '116',
                'school_name' => '加古川校',
                'name_kana' => null,
                'aliases' => ['Kakogawa', 'ECC加古川校'],
                'profile' => [
                    'address' => 'Capil Building 2F, 21-8 Kakogawacho Shinoharacho, Kakogawa-shi, Hyogo 675-0065',
                    'description' => 'Near the south exit of JR Kakogawa Station.',
                    'stations' => [
                        ['station_name' => 'Kakogawa Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'Near the south exit. Capil Building 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '145',
                'school_name' => '桂校',
                'name_kana' => null,
                'aliases' => ['Katsura', 'ECC桂校'],
                'profile' => [
                    'address' => 'West Building 1F, 125 Katsura Minami Tatsumicho, Nishikyo-ku, Kyoto-shi, Kyoto 615-8074',
                    'description' => 'In front of the west exit of Hankyu Katsura Station.',
                    'stations' => [
                        ['station_name' => 'Katsura Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'In front of the west exit. West Building 1F.'],
                    ],
                ],
            ],
            [
                'school_code' => '131',
                'school_name' => '川西校',
                'name_kana' => null,
                'aliases' => ['Kawanishi', 'ECC川西校'],
                'profile' => [
                    'address' => 'La La Grande 2F, 7-18 Chuocho, Kawanishi-shi, Hyogo 666-0016',
                    'description' => 'Near Hankyu/Nose Electric Kawanishi-Noseguchi Station.',
                    'stations' => [
                        ['station_name' => 'Kawanishi-Noseguchi Station', 'line' => 'Hankyu Takarazuka Line', 'walk_minutes' => 1, 'guide_text' => 'Near the north side of the station (Hankyu). La La Grande 2F.'],
                        ['station_name' => 'Kawanishi-Noseguchi Station', 'line' => 'Nose Electric Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the north side of the station (Nose Electric). La La Grande 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1C0',
                'school_name' => 'ECC KIDSなんば校',
                'name_kana' => null,
                'aliases' => ['Kids Namba', 'ECC KIDSなんば校'],
                'profile' => [
                    'address' => 'Namba Plaza Building 9F, 1-5-7 Motomachi, Naniwa-ku, Osaka-shi, Osaka 556-0016',
                    'description' => 'About 1 minute from Exit 32 of Osaka Metro Namba Station.',
                    'stations' => [
                        ['station_name' => 'Namba Station', 'line' => 'Osaka Metro Yotsubashi Line', 'walk_minutes' => 1, 'guide_text' => 'About 1 minute from Exit 32. Namba Plaza Building 9F.'],
                        ['station_name' => 'Namba Station', 'line' => 'Osaka Metro Midosuji Line / Sennichimae Line', 'walk_minutes' => 5, 'guide_text' => 'About 5 minutes from Namba Station (Midosuji/Sennichimae lines). Namba Plaza Building 9F.'],
                        ['station_name' => 'Osaka-Namba Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 5, 'guide_text' => 'About 5 minutes from Osaka-Namba Station (Kintetsu). Namba Plaza Building 9F.'],
                    ],
                ],
            ],
            [
                'school_code' => '111',
                'school_name' => 'ECC KIDS天王寺校',
                'name_kana' => null,
                'aliases' => ['Kids Tennoji', 'ECC KIDS天王寺校'],
                'profile' => [
                    'address' => 'Tennoji MIO Plaza Building 7F, 10-48 Hidenincho, Tennoji-ku, Osaka-shi, Osaka 543-0055',
                    'description' => 'Inside Tennoji MIO Plaza Building 7F.',
                    'stations' => [
                        ['station_name' => 'Tennoji Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'From JR Tennoji Station central ticket gate to the north exit. Tennoji MIO Plaza Building 7F.'],
                        ['station_name' => 'Tennoji Station', 'line' => 'Osaka Metro Midosuji Line / Tanimachi Line', 'walk_minutes' => 1, 'guide_text' => 'From Osaka Metro Tennoji Station. Tennoji MIO Plaza Building 7F.'],
                        ['station_name' => 'Osaka-Abenobashi Station', 'line' => 'Kintetsu Minami-Osaka Line', 'walk_minutes' => 3, 'guide_text' => 'About 3 minutes from Kintetsu Osaka-Abenobashi Station. Tennoji MIO Plaza Building 7F.'],
                    ],
                ],
            ],
            [
                'school_code' => '115',
                'school_name' => '岸和田校',
                'name_kana' => null,
                'aliases' => ['Kishiwada', 'ECC岸和田校'],
                'profile' => [
                    'address' => 'Mitsui Kishiwada Building 2F, 16-15 Miyamotocho, Kishiwada-shi, Osaka 596-0054',
                    'description' => 'In front of the west exit of Nankai Kishiwada Station.',
                    'stations' => [
                        ['station_name' => 'Kishiwada Station', 'line' => 'Nankai Railway', 'walk_minutes' => 1, 'guide_text' => 'In front of the west exit. Mitsui Kishiwada Building 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1H8',
                'school_name' => '北千里校',
                'name_kana' => null,
                'aliases' => ['Kita Senri', 'ECC北千里校'],
                'profile' => [
                    'address' => 'Dios Kita-Senri Building No.2 1F, 4-2-2 Furuedai, Suita-shi, Osaka 565-0874',
                    'description' => 'Near Hankyu Kita-Senri Station rotary.',
                    'stations' => [
                        ['station_name' => 'Kita-Senri Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the station rotary. Dios Kita-Senri Building No.2 1F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1N1',
                'school_name' => '光明池校',
                'name_kana' => null,
                'aliases' => ['Komyoike', 'ECC光明池校'],
                'profile' => [
                    'address' => 'Combox Komyoike Building 1F, 824-36 Murodocho, Izumi-shi, Osaka 594-1101',
                    'description' => 'Near Komyoike Station on the Semboku Rapid Railway.',
                    'stations' => [
                        ['station_name' => 'Komyoike Station', 'line' => 'Semboku Rapid Railway', 'walk_minutes' => 1, 'guide_text' => 'Turn left after exiting the ticket gate. Combox 1F.'],
                    ],
                ],
            ],
            [
                'school_code' => '158',
                'school_name' => '甲子園校',
                'name_kana' => null,
                'aliases' => ['Koshien', 'ECC甲子園校'],
                'profile' => [
                    'address' => 'Corowa Koshien 2F, 3-3 Koshien Takashiocho, Nishinomiya-shi, Hyogo 663-8166',
                    'description' => 'About 2 minutes from Hanshin Koshien Station.',
                    'stations' => [
                        ['station_name' => 'Koshien Station', 'line' => 'Hanshin Railway', 'walk_minutes' => 2, 'guide_text' => 'About 2 minutes from the station. Corowa Koshien 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '103',
                'school_name' => '草津エイスクエア校',
                'name_kana' => null,
                'aliases' => ['Kusatsu', 'ECC草津エイスクエア校'],
                'profile' => [
                    'address' => 'A-Square SARA East Building 2F, 1-23-3 Nishishibukawa, Kusatsu-shi, Shiga 525-0025',
                    'description' => 'Near the west exit of JR Kusatsu Station.',
                    'stations' => [
                        ['station_name' => 'Kusatsu Station', 'line' => 'JR Biwako Line', 'walk_minutes' => 1, 'guide_text' => 'Near the west exit. A-Square SARA East Building 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '119',
                'school_name' => 'くずは校',
                'name_kana' => null,
                'aliases' => ['Kuzuha', 'ECCくずは校'],
                'profile' => [
                    'address' => 'Keihan Kuzuha Station Building South Building 3F, 14-10 Kuzuha Hanazonocho, Hirakata-shi, Osaka 573-1121',
                    'description' => 'Near the south side of Keihan Kuzuha Station.',
                    'stations' => [
                        ['station_name' => 'Kuzuha Station', 'line' => 'Keihan Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the south side of the station. Station Building South Building 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '106',
                'school_name' => '京橋校',
                'name_kana' => null,
                'aliases' => ['Kyobashi', 'ECC京橋校'],
                'profile' => [
                    'address' => 'Keihan Mall Hotel Building 6F, 2-1-38 Higashinodamachi, Miyakojima-ku, Osaka-shi, Osaka 534-0024',
                    'description' => 'Inside Keihan Mall Hotel Building 6F, near Kyobashi Station.',
                    'stations' => [
                        ['station_name' => 'Kyobashi Station', 'line' => 'JR Osaka Loop Line / JR Tozai Line', 'walk_minutes' => 1, 'guide_text' => 'Inside Keihan Mall Hotel Building 6F (JR).'],
                        ['station_name' => 'Kyobashi Station', 'line' => 'Keihan Railway', 'walk_minutes' => 1, 'guide_text' => 'Inside Keihan Mall Hotel Building 6F (Keihan).'],
                        ['station_name' => 'Kyobashi Station', 'line' => 'Osaka Metro Nagahori Tsurumi-ryokuchi Line', 'walk_minutes' => 1, 'guide_text' => 'Inside Keihan Mall Hotel Building 6F (Osaka Metro).'],
                    ],
                ],
            ],
            [
                'school_code' => '135',
                'school_name' => '京都駅前校',
                'name_kana' => null,
                'aliases' => ['Kyoto Ekimae', 'ECC京都駅前校'],
                'profile' => [
                    'address' => 'Torii Building 6F, 718 Higashishiokojicho, Shimogyo-ku, Kyoto-shi, Kyoto 600-8216',
                    'description' => 'In front of JR/Kintetsu/Subway Kyoto Station.',
                    'stations' => [
                        ['station_name' => 'Kyoto Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'In front of the station (JR). Torii Building 6F.'],
                        ['station_name' => 'Kyoto Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'In front of the station (Kintetsu). Torii Building 6F.'],
                        ['station_name' => 'Kyoto Station', 'line' => 'Kyoto Municipal Subway', 'walk_minutes' => 1, 'guide_text' => 'In front of the station (Subway). Torii Building 6F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1N4',
                'school_name' => '南草津校',
                'name_kana' => null,
                'aliases' => ['Minami Kusatsu', 'ECC南草津校'],
                'profile' => [
                    'address' => 'Ferie Minami-Kusatsu 4F, 1-15-5 Noji, Kusatsu-shi, Shiga 525-0059',
                    'description' => 'Near the east exit of JR Minami-Kusatsu Station.',
                    'stations' => [
                        ['station_name' => 'Minami-Kusatsu Station', 'line' => 'JR Biwako Line', 'walk_minutes' => 1, 'guide_text' => 'Near the east exit. Ferie Minami-Kusatsu 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '412',
                'school_name' => 'マークイズ福岡ももち校',
                'name_kana' => null,
                'aliases' => ['Momochi', 'ECCマークイズ福岡ももち校'],
                'profile' => [
                    'address' => 'MARK IS Fukuoka Momochi 4F, 2-2-1 Jigyohama, Chuo-ku, Fukuoka-shi, Fukuoka 810-8639',
                    'description' => 'Inside MARK IS Fukuoka Momochi 4F.',
                    'stations' => [
                        ['station_name' => 'Tojinmachi Station', 'line' => 'Fukuoka City Subway', 'walk_minutes' => 10, 'guide_text' => 'About 10 minutes on foot. MARK IS Fukuoka Momochi 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1N7',
                'school_name' => '名谷校',
                'name_kana' => null,
                'aliases' => ['Myodani', 'ECC名谷校'],
                'profile' => [
                    'address' => 'Myodani Center Building 2F, 2-2-5 Nakaochiai, Suma-ku, Kobe-shi, Hyogo 654-0154',
                    'description' => 'Near Myodani Station on the Kobe Municipal Subway.',
                    'stations' => [
                        ['station_name' => 'Myodani Station', 'line' => 'Kobe Municipal Subway', 'walk_minutes' => 1, 'guide_text' => 'Exit the ticket gate and turn left toward the south exit. Myodani Center Building 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1A6',
                'school_name' => '長岡天神校',
                'name_kana' => null,
                'aliases' => ['Nagaokatenjin', 'ECC長岡天神校'],
                'profile' => [
                    'address' => '1-1-5 Tenjin, Nagaokakyo-shi, Kyoto 617-0824',
                    'description' => 'In front of the west exit of Hankyu Nagaoka-Tenjin Station.',
                    'stations' => [
                        ['station_name' => 'Nagaoka-Tenjin Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'In front of the west exit.'],
                    ],
                ],
            ],
            [
                'school_code' => '146',
                'school_name' => '中もず校',
                'name_kana' => null,
                'aliases' => ['Nakamozu', 'ECC中もず校'],
                'profile' => [
                    'address' => 'Koei Daiichi Building 3F, 2-101 Nakamozucho, Kita-ku, Sakai-shi, Osaka 591-8023',
                    'description' => 'Right next to Exit 8 of Osaka Metro/Nankai Nakamozu Station.',
                    'stations' => [
                        ['station_name' => 'Nakamozu Station', 'line' => 'Osaka Metro Midosuji Line', 'walk_minutes' => 1, 'guide_text' => 'Right next to Exit 8 (Osaka Metro). Koei Daiichi Building 3F.'],
                        ['station_name' => 'Nakamozu Station', 'line' => 'Nankai Koya Line', 'walk_minutes' => 1, 'guide_text' => 'Near the station (Nankai). Koei Daiichi Building 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '110',
                'school_name' => '寝屋川校',
                'name_kana' => null,
                'aliases' => ['Neyagawa', 'ECC寝屋川校'],
                'profile' => [
                    'address' => 'Chiyoda Building 4F, 23-12 Hayakocho, Neyagawa-shi, Osaka 572-0837',
                    'description' => 'Near the south exit of Keihan Neyagawashi Station.',
                    'stations' => [
                        ['station_name' => 'Neyagawashi Station', 'line' => 'Keihan Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the south exit. Chiyoda Building 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '113',
                'school_name' => '西宮北口校',
                'name_kana' => null,
                'aliases' => ['Nishinomiya', 'ECC西宮北口校'],
                'profile' => [
                    'address' => 'Paseo Estacion 3F, 3-34 Takamatsucho, Nishinomiya-shi, Hyogo 663-8204',
                    'description' => 'Directly connected to the south ticket gate of Hankyu Nishinomiya-Kitaguchi Station.',
                    'stations' => [
                        ['station_name' => 'Nishinomiya-Kitaguchi Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Directly connected to the south ticket gate. Paseo Estacion 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '2Z1',
                'school_name' => 'オンライン',
                'name_kana' => null,
                'aliases' => ['Online'],
                'profile' => [
                    'address' => 'ECC Head Office Building 9F, 1-10-20 Higashi Tenma, Kita-ku, Osaka-shi, Osaka 530-0044',
                    'description' => 'Online office on the 9th floor of ECC Head Office.',
                    'stations' => $hqStations,
                ],
            ],
            [
                'school_code' => '407',
                'school_name' => 'イオンモール岡山校',
                'name_kana' => null,
                'aliases' => ['Okayama', 'ECCイオンモール岡山校'],
                'profile' => [
                    'address' => 'AEON Mall Okayama 5F, 1-2-1-5023 Shimoishii, Kita-ku, Okayama-shi, Okayama 700-0907',
                    'description' => 'Inside AEON Mall Okayama 5F, near JR Okayama Station.',
                    'stations' => [
                        ['station_name' => 'Okayama Station', 'line' => 'JR', 'walk_minutes' => 5, 'guide_text' => 'Near JR Okayama Station. AEON Mall Okayama 5F.'],
                    ],
                ],
            ],
            [
                'school_code' => '120',
                'school_name' => '西大寺校',
                'name_kana' => null,
                'aliases' => ['Saidaiji', 'ECC西大寺校'],
                'profile' => [
                    'address' => 'Sanwa City Saidaiji 4F, 2-1-63 Saidaiji Higashimachi, Nara-shi, Nara 631-0821',
                    'description' => 'In front of the north exit of Kintetsu Yamato-Saidaiji Station.',
                    'stations' => [
                        ['station_name' => 'Yamato-Saidaiji Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'In front of the north exit. Sanwa City Saidaiji 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '114',
                'school_name' => '堺東校',
                'name_kana' => null,
                'aliases' => ['Sakai Higashi', 'ECC堺東校'],
                'profile' => [
                    'address' => 'Gendai Sakaihigashi Ekimae Building 4F, 2-4-18 Kitakawaramachi, Sakai-ku, Sakai-shi, Osaka 590-0076',
                    'description' => 'Near Nankai Sakaihigashi Station.',
                    'stations' => [
                        ['station_name' => 'Sakaihigashi Station', 'line' => 'Nankai Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the station. Gendai Sakaihigashi Ekimae Building 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1N2',
                'school_name' => '三田駅前校',
                'name_kana' => null,
                'aliases' => ['Sanda Ekimae', 'ECC三田駅前校'],
                'profile' => [
                    'address' => 'Sanda Ekimae Ichibankan 5F, 2-1 Ekimaecho, Sanda-shi, Hyogo 669-1528',
                    'description' => 'Directly connected to the south exit of JR Sanda Station.',
                    'stations' => [
                        ['station_name' => 'Sanda Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'Directly connected to the south exit. Sanda Ekimae Ichibankan 5F.'],
                    ],
                ],
            ],
            [
                'school_code' => '107',
                'school_name' => '三宮校',
                'name_kana' => null,
                'aliases' => ['Sannomiya', 'ECC三宮校'],
                'profile' => [
                    'address' => 'Kobe Kotsu Center Building 3F, 1-10-1 Sannomiyacho, Chuo-ku, Kobe-shi, Hyogo 650-0021',
                    'description' => 'Near JR/Hankyu/Hanshin Sannomiya Station and Kobe Municipal Subway.',
                    'stations' => [
                        ['station_name' => 'Sannomiya Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'Near JR Sannomiya west ticket gate. Kobe Kotsu Center Building 3F.'],
                        ['station_name' => 'Kobe-Sannomiya Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near Hankyu Kobe-Sannomiya east ticket gate. Kobe Kotsu Center Building 3F.'],
                        ['station_name' => 'Kobe-Sannomiya Station', 'line' => 'Hanshin Railway', 'walk_minutes' => 3, 'guide_text' => 'About 3 minutes from Hanshin Kobe-Sannomiya Station. Kobe Kotsu Center Building 3F.'],
                        ['station_name' => 'Sannomiya Station', 'line' => 'Kobe Municipal Subway', 'walk_minutes' => 3, 'guide_text' => 'About 3 minutes from Kobe Municipal Subway Sannomiya Station. Kobe Kotsu Center Building 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1H0',
                'school_name' => '千里中央校',
                'name_kana' => null,
                'aliases' => ['Senri Chuo', 'ECC千里中央校'],
                'profile' => [
                    'address' => 'Hankyu Senri-Chuo Building 10F, 1-4-1 Shinsenri Higashimachi, Toyonaka-shi, Osaka 560-0082',
                    'description' => 'Next to Senri Hankyu Department Store, served by Kita-Osaka Kyuko and Osaka Monorail.',
                    'stations' => [
                        ['station_name' => 'Senri-Chuo Station', 'line' => 'Kita-Osaka Kyuko Railway', 'walk_minutes' => 1, 'guide_text' => 'Next to Senri Hankyu Department Store (Kita-Osaka Kyuko). Hankyu Senri-Chuo Building 10F.'],
                        ['station_name' => 'Senri-Chuo Station', 'line' => 'Osaka Monorail', 'walk_minutes' => 1, 'guide_text' => 'Next to Senri Hankyu Department Store (Osaka Monorail). Hankyu Senri-Chuo Building 10F.'],
                    ],
                ],
            ],
            [
                'school_code' => '137',
                'school_name' => '四条烏丸校',
                'name_kana' => null,
                'aliases' => ['Shijo', 'ECC四条烏丸校'],
                'profile' => [
                    'address' => 'Kyoto Zero Gate 5F, 83-1 Tachiuri Nakanocho, Shimogyo-ku, Kyoto-shi, Kyoto 600-8006',
                    'description' => 'Near Hankyu Karasuma Station and Kyoto Municipal Subway Shijo Station.',
                    'stations' => [
                        ['station_name' => 'Karasuma Station', 'line' => 'Hankyu Kyoto Line', 'walk_minutes' => 1, 'guide_text' => 'Near Exit 16 (Hankyu). Kyoto Zero Gate 5F.'],
                        ['station_name' => 'Shijo Station', 'line' => 'Kyoto Municipal Subway Karasuma Line', 'walk_minutes' => 1, 'guide_text' => 'Near Exit 5 (Subway). Kyoto Zero Gate 5F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1H5',
                'school_name' => '新田辺校',
                'name_kana' => null,
                'aliases' => ['Shin Tanabe', 'ECC新田辺校'],
                'profile' => [
                    'address' => 'Kintetsu Shin-Tanabe West Building 2F, 6-3-1 Tanabe Chuo, Kyotanabe-shi, Kyoto 610-0334',
                    'description' => 'Near the west exit of Kintetsu Shin-Tanabe Station.',
                    'stations' => [
                        ['station_name' => 'Shin-Tanabe Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the west exit, inside the rotary. Kintetsu Shin-Tanabe West Building 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1N5',
                'school_name' => '白庭台校',
                'name_kana' => null,
                'aliases' => ['Shiraniwadai', 'ECC白庭台校'],
                'profile' => [
                    'address' => 'SOLTE Shiraniwadai 2F, 6-12-1 Shiraniwadai, Ikoma-shi, Nara 630-0136',
                    'description' => 'Near the north side of Kintetsu Shiraniwadai Station.',
                    'stations' => [
                        ['station_name' => 'Shiraniwadai Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the north side, in front of the bus rotary. SOLTE Shiraniwadai 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1C1',
                'school_name' => '心斎橋校',
                'name_kana' => null,
                'aliases' => ['Shinsaibashi', 'ECC心斎橋校'],
                'profile' => [
                    'address' => 'Midosuji Building 8F, 1-4-5 Nishishinsaibashi, Chuo-ku, Osaka-shi, Osaka 542-0086',
                    'description' => 'Right near Exit 7 of Shinsaibashi Station on the Osaka Metro Midosuji/Nagahori lines.',
                    'stations' => [
                        ['station_name' => 'Shinsaibashi Station', 'line' => 'Osaka Metro Midosuji Line', 'walk_minutes' => 1, 'guide_text' => 'Right near Exit 7 (Midosuji Line). Midosuji Building 8F.'],
                        ['station_name' => 'Shinsaibashi Station', 'line' => 'Osaka Metro Nagahori Tsurumi-ryokuchi Line', 'walk_minutes' => 1, 'guide_text' => 'Right near the station (Nagahori Line). Midosuji Building 8F.'],
                    ],
                ],
            ],
            [
                'school_code' => '133',
                'school_name' => '夙川校',
                'name_kana' => null,
                'aliases' => ['Shukugawa', 'ECC夙川校'],
                'profile' => [
                    'address' => 'Amista Shukugawa 3F, 10-5 Hagoromocho, Nishinomiya-shi, Hyogo 662-0051',
                    'description' => 'Near the south exit of Hankyu Shukugawa Station.',
                    'stations' => [
                        ['station_name' => 'Shukugawa Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the south exit. Amista Shukugawa 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '130',
                'school_name' => '神戸住吉校',
                'name_kana' => null,
                'aliases' => ['Sumiyoshi', 'ECC神戸住吉校'],
                'profile' => [
                    'address' => 'JR Sumiyoshi KiLaLa Izumi Building 5F, 4-4-2 Sumiyoshi Miyamachi, Higashinada-ku, Kobe-shi, Hyogo 658-0053',
                    'description' => 'Near the south exit of JR Sumiyoshi Station.',
                    'stations' => [
                        ['station_name' => 'Sumiyoshi Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'Exit the south exit and turn left. JR Sumiyoshi KiLaLa Izumi Building 5F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1N8',
                'school_name' => 'イオンモール高の原校',
                'name_kana' => null,
                'aliases' => ['Takanohara', 'ECCイオンモール高の原校'],
                'profile' => [
                    'address' => 'AEON Mall Takanohara 3F, 1-1-1 Saganakadai, Kizugawa-shi, Kyoto 619-0223',
                    'description' => 'Inside AEON Mall Takanohara 3F.',
                    'stations' => [
                        ['station_name' => 'Takanohara Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Inside AEON Mall Takanohara 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1N9',
                'school_name' => 'ECC KIDS高槻校',
                'name_kana' => null,
                'aliases' => ['Takatsuki Kids', 'ECC KIDS高槻校'],
                'profile' => [
                    'address' => 'Act Amore 1F, 1-2 Akutagawacho, Takatsuki-shi, Osaka 569-1123',
                    'description' => 'Directly connected to the central north exit of JR Takatsuki Station.',
                    'stations' => [
                        ['station_name' => 'Takatsuki Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'Directly connected to the central north exit. Act Amore 1F.'],
                    ],
                ],
            ],
            [
                'school_code' => '122',
                'school_name' => 'グリーンプラザ高槻校',
                'name_kana' => null,
                'aliases' => ['Takatsuki Green Plaza', 'ECCグリーンプラザ高槻校'],
                'profile' => [
                    'address' => 'Green Plaza Takatsuki Building No.1 4F, 1-1 Konyamachi, Takatsuki-shi, Osaka 569-0804',
                    'description' => 'Directly connected to the central south exit of JR Takatsuki Station.',
                    'stations' => [
                        ['station_name' => 'Takatsuki Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'Directly connected to the central south exit. Green Plaza Takatsuki Building No.1 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1A4',
                'school_name' => '垂水校',
                'name_kana' => null,
                'aliases' => ['Tarumi', 'ECC垂水校'],
                'profile' => [
                    'address' => 'Mikuni Building 4F, 4-13 Kandacho, Tarumi-ku, Kobe-shi, Hyogo 655-0027',
                    'description' => 'Near the west exit of JR/Sanyo Tarumi Station.',
                    'stations' => [
                        ['station_name' => 'Tarumi Station', 'line' => 'JR Sanyo Main Line', 'walk_minutes' => 1, 'guide_text' => 'Near the mountain side of the west exit (JR). Mikuni Building 4F.'],
                        ['station_name' => 'Tarumi Station', 'line' => 'Sanyo Electric Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the mountain side of the west exit (Sanyo). Mikuni Building 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '401',
                'school_name' => '福岡天神ソラリアステージ校',
                'name_kana' => null,
                'aliases' => ['Teijin Solaria', 'Tenjin Solaria', 'ECC福岡天神ソラリアステージ校'],
                'profile' => [
                    'address' => 'Solaria Stage Building 5F, 2-11-3 Tenjin, Chuo-ku, Fukuoka-shi, Fukuoka 810-0001',
                    'description' => 'Directly connected to Nishitetsu Fukuoka Tenjin Station, near Subway Tenjin Station.',
                    'stations' => [
                        ['station_name' => 'Nishitetsu Fukuoka (Tenjin) Station', 'line' => 'Nishitetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Directly connected to the station. Solaria Stage Building 5F.'],
                        ['station_name' => 'Tenjin Station', 'line' => 'Fukuoka City Subway Kuko Line', 'walk_minutes' => 3, 'guide_text' => 'About 3 minutes from Subway Tenjin Station. Solaria Stage Building 5F.'],
                    ],
                ],
            ],
            [
                'school_code' => '109',
                'school_name' => '天王寺ミオプラザ校',
                'name_kana' => null,
                'aliases' => ['Tennoji MP', 'ECC天王寺ミオプラザ校'],
                'profile' => [
                    'address' => 'Tennoji MIO Plaza Building 7F, 10-48 Hidenincho, Tennoji-ku, Osaka-shi, Osaka 543-0055',
                    'description' => 'Inside Tennoji MIO Plaza Building 7F.',
                    'stations' => [
                        ['station_name' => 'Tennoji Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'From JR Tennoji Station central ticket gate to the north exit. Tennoji MIO Plaza Building 7F.'],
                        ['station_name' => 'Tennoji Station', 'line' => 'Osaka Metro Midosuji Line / Tanimachi Line', 'walk_minutes' => 1, 'guide_text' => 'From Osaka Metro Tennoji Station. Tennoji MIO Plaza Building 7F.'],
                        ['station_name' => 'Osaka-Abenobashi Station', 'line' => 'Kintetsu Minami-Osaka Line', 'walk_minutes' => 3, 'guide_text' => 'About 3 minutes from Kintetsu Osaka-Abenobashi Station. Tennoji MIO Plaza Building 7F.'],
                    ],
                ],
            ],
            [
                'school_code' => '1N6',
                'school_name' => 'イオンモール奈良登美ヶ丘校',
                'name_kana' => null,
                'aliases' => ['Tomigaoka', 'ECCイオンモール奈良登美ヶ丘校'],
                'profile' => [
                    'address' => 'AEON Mall Nara Tomigaoka 2F, 3027 Shikahatacho, Ikoma-shi, Nara 630-0115',
                    'description' => 'Inside AEON Mall Nara Tomigaoka 2F.',
                    'stations' => [
                        ['station_name' => 'Gakken Nara-Tomigaoka Station', 'line' => 'Kintetsu Keihanna Line', 'walk_minutes' => 1, 'guide_text' => 'Inside AEON Mall Nara Tomigaoka 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '123',
                'school_name' => '豊中校',
                'name_kana' => null,
                'aliases' => ['Toyonaka', 'ECC豊中校'],
                'profile' => [
                    'address' => 'Toyonaka Daiichi Building 5F, 1-10-1 Honmachi, Toyonaka-shi, Osaka 560-0021',
                    'description' => 'Connected by pedestrian bridge from Hankyu Toyonaka Station.',
                    'stations' => [
                        ['station_name' => 'Toyonaka Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Connected by pedestrian bridge from Exit 5 of the south ticket gate. Toyonaka Daiichi Building 5F.'],
                    ],
                ],
            ],
            [
                'school_code' => '136',
                'school_name' => '阪急グランドビル梅田校',
                'name_kana' => null,
                'aliases' => ['Umeda GB', 'ECC阪急グランドビル梅田校'],
                'profile' => [
                    'address' => 'Hankyu Grand Building 24F, 8-47 Kakudacho, Kita-ku, Osaka-shi, Osaka 530-0017',
                    'description' => 'Near Hankyu/Hanshin Osaka-Umeda Station and JR Osaka Station.',
                    'stations' => [
                        ['station_name' => 'Osaka-Umeda Station', 'line' => 'Hankyu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near Hankyu Osaka-Umeda Station. Hankyu Grand Building 24F.'],
                        ['station_name' => 'Osaka-Umeda Station', 'line' => 'Hanshin Railway', 'walk_minutes' => 5, 'guide_text' => 'About 5 minutes from Hanshin Osaka-Umeda Station. Hankyu Grand Building 24F.'],
                        ['station_name' => 'Umeda Station', 'line' => 'Osaka Metro Midosuji Line', 'walk_minutes' => 5, 'guide_text' => 'About 5 minutes from Osaka Metro Umeda Station. Hankyu Grand Building 24F.'],
                        ['station_name' => 'Osaka Station', 'line' => 'JR', 'walk_minutes' => 5, 'guide_text' => 'About 5 minutes from JR Osaka Station. Hankyu Grand Building 24F.'],
                    ],
                ],
            ],
            [
                'school_code' => '128',
                'school_name' => '和歌山ミオ校',
                'name_kana' => null,
                'aliases' => ['Wakayama', 'ECC和歌山ミオ校'],
                'profile' => [
                    'address' => 'Wakayama MIO 3F, 5-61 Misonocho, Wakayama-shi, Wakayama 640-8331',
                    'description' => 'Directly connected to JR Wakayama Station.',
                    'stations' => [
                        ['station_name' => 'Wakayama Station', 'line' => 'JR', 'walk_minutes' => 1, 'guide_text' => 'Directly connected to JR Wakayama Station. Wakayama MIO 3F.'],
                    ],
                ],
            ],
            [
                'school_code' => '164',
                'school_name' => '八木校',
                'name_kana' => null,
                'aliases' => ['Yagi', 'ECC八木校'],
                'profile' => [
                    'address' => 'Daini Nakatani Building 2F, 5-2-34 Naizencho, Kashihara-shi, Nara 634-0804',
                    'description' => 'Near the north exit of Kintetsu Yamato-Yagi Station.',
                    'stations' => [
                        ['station_name' => 'Yamato-Yagi Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the north exit. Daini Nakatani Building 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => '150',
                'school_name' => '山科校',
                'name_kana' => null,
                'aliases' => ['Yamashina', 'ECC山科校'],
                'profile' => [
                    'address' => 'Swan Yamashina Building 4F, 5-9 Anshu Naka Koji-cho, Yamashina-ku, Kyoto-shi, Kyoto 607-8013',
                    'description' => 'Near JR/Keihan Yamashina Station.',
                    'stations' => [
                        ['station_name' => 'Yamashina Station', 'line' => 'JR Biwako Line / Kosei Line', 'walk_minutes' => 1, 'guide_text' => 'Near the south side of the station (JR). Swan Yamashina Building 4F.'],
                        ['station_name' => 'Yamashina Station', 'line' => 'Keihan Oto Line', 'walk_minutes' => 1, 'guide_text' => 'Near the south side of the station (Keihan). Swan Yamashina Building 4F.'],
                    ],
                ],
            ],
            [
                'school_code' => '144',
                'school_name' => '八尾校',
                'name_kana' => null,
                'aliases' => ['Yao', 'ECC八尾校'],
                'profile' => [
                    'address' => 'Yao Fujimasu Kousan Building 2F, 2-69 Hikaricho, Yao-shi, Osaka 581-0803',
                    'description' => 'Near the north exit of Kintetsu Yao Station.',
                    'stations' => [
                        ['station_name' => 'Kintetsu Yao Station', 'line' => 'Kintetsu Railway', 'walk_minutes' => 1, 'guide_text' => 'Near the north exit. Yao Fujimasu Kousan Building 2F.'],
                    ],
                ],
            ],
            [
                'school_code' => 'HQ',
                'school_name' => '本社',
                'name_kana' => null,
                'aliases' => ['HQ', 'ECC本社'],
                'profile' => [
                    'address' => 'ECC Head Office Building, 1-10-20 Higashi Tenma, Kita-ku, Osaka-shi, Osaka 530-0044',
                    'description' => 'ECC Head Office.',
                    'stations' => $hqStations,
                ],
            ],
            [
                'school_code' => 'NED',
                'school_name' => '教務',
                'name_kana' => null,
                'aliases' => ['Education'],
                'profile' => [
                    'address' => 'ECC Head Office Building 8F, 1-10-20 Higashi Tenma, Kita-ku, Osaka-shi, Osaka 530-0044',
                    'description' => 'Education department on the 8th floor of ECC Head Office.',
                    'stations' => $hqStations,
                ],
            ],
            [
                'school_code' => 'NHR',
                'school_name' => '人事',
                'name_kana' => null,
                'aliases' => ['Personal'],
                'profile' => [
                    'address' => 'ECC Head Office Building 8F, 1-10-20 Higashi Tenma, Kita-ku, Osaka-shi, Osaka 530-0044',
                    'description' => 'HR department on the 8th floor of ECC Head Office.',
                    'stations' => $hqStations,
                ],
            ],
        ];

        DB::transaction(function () use ($schools, $now, $profileMapImage, $stationGuideImage) {
            foreach ($schools as $row) {
                DB::table('schools')->updateOrInsert(
                    ['school_code' => $row['school_code']],
                    [
                        'school_name' => $row['school_name'],
                        'name_kana' => $row['name_kana'],
                        'aliases' => json_encode($row['aliases'], JSON_UNESCAPED_UNICODE),
                        'is_active' => true,
                        'deleted_at' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $school = DB::table('schools')
                    ->where('school_code', $row['school_code'])
                    ->first();

                if (!$school || empty($row['profile'])) {
                    continue;
                }

                $profile = DB::table('school_profiles')
                    ->where('school_id', $school->id)
                    ->whereNull('valid_to')
                    ->first();

                // 既存データの画像を統一するため、map_image_path を更新時・新規時どちらも payload に含める
                $profilePayload = [
                    'school_id' => $school->id,
                    'address' => $row['profile']['address'],
                    'description' => $row['profile']['description'] ?? null,
                    'station_url' => $this->googleMapUrl($row['school_name']),
                    'valid_from' => $profile->valid_from ?? $now,
                    'valid_to' => null,
                    'map_image_path' => $profileMapImage,
                    'updated_at' => $now,
                ];

                if ($profile) {
                    DB::table('school_profiles')
                        ->where('id', $profile->id)
                        ->update($profilePayload);

                    $profileId = $profile->id;
                } else {
                    $profilePayload['created_at'] = $now;

                    $profileId = DB::table('school_profiles')->insertGetId($profilePayload);
                }

                foreach (($row['profile']['stations'] ?? []) as $index => $station) {
                    // sort_order のみで既存レコードを特定（station 並び替え・追加に対応）
                    $existingStation = DB::table('school_stations')
                        ->where('school_profile_id', $profileId)
                        ->where('sort_order', $index)
                        ->first();

                    // 既存データの画像を統一するため、guide_image_path を更新時・新規時どちらも payload に含める
                    $stationPayload = [
                        'school_profile_id' => $profileId,
                        'station_name' => $station['station_name'],
                        'line' => $station['line'] ?? null,
                        'walk_minutes' => $station['walk_minutes'] ?? null,
                        'guide_text' => $station['guide_text'] ?? null,
                        'sort_order' => $index,
                        'guide_image_path' => $stationGuideImage,
                        'updated_at' => $now,
                    ];

                    if ($existingStation) {
                        DB::table('school_stations')
                            ->where('id', $existingStation->id)
                            ->update($stationPayload);
                    } else {
                        $stationPayload['created_at'] = $now;

                        DB::table('school_stations')->insert($stationPayload);
                    }
                }
            }
        });
    }

    private function googleMapUrl(string $schoolName): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode('ECC外語学院 ' . $schoolName);
    }
}
