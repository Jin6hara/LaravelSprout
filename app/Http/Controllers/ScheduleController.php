<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\TimeString;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $viewer = Auth::user();

        // admin / super_admin のみ全ユーザー閲覧可能
        if (!$viewer->hasRole(['admin', 'super_admin'])) {
            abort(403, 'You are not authorized to view all schedules.');
        }

        // 全ユーザーをセレクトボックス用に取得
        $userOptions = User::query()
            ->orderBy('first_name')
            ->orderBy('family_name')
            ->get(['id', 'first_name', 'family_name', 'employee_code']);

        $query = Schedule::query()->with('user');

        // === フィルタ ===
        // Active On: 指定日が範囲に含まれる
        if ($request->filled('active_on')) {
            $on = TimeString::normalizeToYmd($request->input('active_on'));
            $query->whereDate('effective_start', '<=', $on)
                ->whereDate('effective_end', '>=', $on);
        }

        //  Active Until: 指定日以前に終了したスケジュール
        if ($request->filled('active_until')) {
            $until = TimeString::normalizeToYmd($request->input('active_until'));
            $query->whereDate('effective_end', '<=', $until);
        }
        
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('username')) {
            $name = $request->input('username');
            $query->whereHas('user', function ($q) use ($name) {
                $q->where('first_name', 'like', "%{$name}%")
                    ->orWhere('family_name', 'like', "%{$name}%");
            });
        }

        if ($request->filled('active')) {
            $active = $request->boolean('active');
            $query->where('is_active', $active);
        }

        if ($request->filled('label')) {
            $label = $request->input('label');
            $query->where('label', 'like', "%{$label}%");
        }

        // === ソート ===
        $schedules = $query
            ->orderBy('user_id')
            ->orderByDesc('effective_start')
            ->get();

        return view('schedule.schedule', compact('schedules', 'userOptions'));
    }

    public function update(Request $request, Schedule $schedule)
    {
        // 1) まず Y-m-d へ正規化（空なら null も許容したい場合は調整）
        $request->merge([
            'effective_start' => TimeString::normalizeToYmd($request->input('effective_start')),
            'effective_end'   => TimeString::normalizeToYmd($request->input('effective_end')),
            // select から来る "0"/"1" を boolean に寄せる
            'is_active'       => (int) $request->boolean('is_active'),
        ]);

        // 2) 厳格に "Y-m-d" を要求
        $validated = $request->validate([
            'label'           => ['nullable', 'string', 'max:255'],
            'effective_start' => ['required', 'date_format:Y-m-d'],
            'effective_end'   => ['required', 'date_format:Y-m-d', 'after_or_equal:effective_start'],
            'is_active'       => ['required', 'boolean'],
        ]);

        // 3) 保存
        $schedule->update($validated);

        return back()->with('status', 'Schedule updated successfully.');
    }
}
