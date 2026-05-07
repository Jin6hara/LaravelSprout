<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolProfileController extends Controller
{
    /**
     * 学校検索 + 現行プロファイルをカード表示
     * GET /schools/search?q=...
     */
    public function search(Request $request)
    {
        $q = trim((string)$request->input('q', ''));
        $perPage = (int)($request->input('per_page', 12)) ?: 12;

        $query = School::query()
            ->where('is_active', true)
            // 検索条件
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->whereLikeInsensitive('school_name', $q)
                      ->orWhereLikeInsensitive('name_kana', $q)
                      ->orWhereLikeInsensitive('school_code', $q)
                      ->orWhereLikeInsensitive('aliases', $q);
                });
            })
            // 現行プロファイル + 駅リストを eager load
            ->with([
                'currentProfile' => function ($p) {
                    $p->select('id','school_id','address','description','map_image_path','station_url', 'valid_from','valid_to');
                },
                'currentProfile.stations' => function ($s) {
                    $s->select('id','school_profile_id','station_name','line','walk_minutes','guide_text','guide_image_path','sort_order')
                      ->orderBy('sort_order');
                },
            ])
            // 現行プロファイルが存在するものだけに絞る（任意）
            ->whereHas('currentProfile', fn($h) => $h->whereNull('valid_to'))
            ->orderBy('school_name');

        $schools = $query->paginate($perPage)->withQueryString();

        return view('schools.search', [
            'q'       => $q,
            'schools' => $schools,
        ]);
    }
}
