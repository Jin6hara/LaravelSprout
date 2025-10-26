<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\TimeString;

class ScheduleController extends Controller
{
    public function index()
    {
        $viewer = Auth::user();

        // admin / super_admin のみ全ユーザー閲覧可能
        if (!$viewer->hasRole(['admin', 'super_admin'])) {
            abort(403, 'You are not authorized to view all schedules.');
        }

        // 全ユーザーのスケジュール一覧（ユーザー別・期間降順）
        $schedules = Schedule::query()
            ->with('user')
            ->orderBy('user_id')
            ->orderByDesc('effective_start')
            ->get();

        return view('schedule.schedule', compact('schedules'));
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
