<?php
/**
 * 各種 CSV インポート・エクスポート機能へのリンク一覧画面を担うコントローラ。
 */
namespace App\Http\Controllers;

use App\Models\User;

class CsvController extends Controller
{
    /**
     * CSV 管理メニュー画面
     * GET /csv — ユーザー・レッスン・スケジュールなど各 CSV 機能へのリンク一覧を表示
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);
        return view('csv.csv_list');
    }
}
