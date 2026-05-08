<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\User;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\TimeString;

class ScheduleController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    public function index(Request $request)
    {
        $viewer = Auth::user();

        // admin / super_admin のみ全ユーザー閲覧可能
        if (!$viewer->hasRole(['admin', 'super_admin'])) {
            abort(403, 'You are not authorized to view all schedules.');
        }

        // 全ユーザーをセレクトボックス用に取得
        $userOptions = $this->scopeService->targetUserQuery()
            ->orderBy('first_name')
            ->orderBy('family_name')
            ->get(['id', 'first_name', 'family_name', 'employee_code']);

        // 追加：新規追加モーダル用「追加可能ユーザー」
        // 条件：
        // 1) 在籍（start_date <= today && (end_date is null || end_date >= today) の term が存在）
        // 2) 在籍でない場合、start_date が today〜today+1month の term が存在
        $today = now()->toDateString();
        $inOneMonth = now()->addMonth()->toDateString();

        $eligibleUsers = $this->scopeService->targetUserQuery()
            ->where(function ($q) use ($today, $inOneMonth) {
                $q->whereExists(function ($sq) use ($today) {
                    $sq->from('employment_terms as et')
                        ->whereColumn('et.user_id', 'users.id')
                        ->whereDate('et.start_date', '<=', $today)
                        ->where(function ($sq2) use ($today) {
                            $sq2->whereNull('et.end_date')->orWhereDate('et.end_date', '>=', $today);
                        });
                })
                    ->orWhereExists(function ($sq) use ($today, $inOneMonth) {
                        $sq->from('employment_terms as et')
                            ->whereColumn('et.user_id', 'users.id')
                            ->whereDate('et.start_date', '>', $today)
                            ->whereDate('et.start_date', '<=', $inOneMonth);
                    });
            })
            ->orderBy('first_name')
            ->orderBy('family_name')
            ->get(['id', 'first_name', 'family_name', 'employee_code']);

        $query = Schedule::query()->with('user')
            ->whereIn('user_id', $this->scopeService->targetUserIds());

        // === フィルタ ===
        // Active On: 指定日が範囲に含まれる
        if ($request->filled('active_on') && $request->filled('active_until')) {
            // ★ 両方指定 → 期間重複検索（overlap）
            $from = TimeString::normalizeToYmd($request->input('active_on'));
            $to   = TimeString::normalizeToYmd($request->input('active_until'));
            // 重なり条件: schedule.start <= to AND schedule.end >= from
            $query->whereDate('effective_start', '<=', $to)
                ->whereDate('effective_end', '>=', $from);
        } else {
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
        }

        // ▼ user_id での絞り込み（検索フォームからの選択）
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('username')) {
            $name = $request->input('username');
            $query->whereHas('user', function ($q) use ($name) {
                $q->whereLikeInsensitive('first_name', $name)
                    ->orWhereLikeInsensitive('family_name', $name);
            });
        }

        if ($request->filled('active')) {
            $active = $request->boolean('active');
            $query->where('is_active', $active);
        }

        if ($request->filled('label')) {
            $label = $request->input('label');
            $query->whereLikeInsensitive('label', $label);
        }

        // === ソート ===
        $schedules = $query
            ->orderBy('user_id')
            ->orderByDesc('effective_start')
            ->get();


        return view('schedule.schedule', compact('schedules', 'userOptions', 'eligibleUsers'));
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

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            // 'label' => ['nullable','string','max:255'], // ラベルをモーダルで入れるなら使用
        ]);

        $uid = (int) $request->input('user_id');
        $today = now()->toDateString();
        $inOneMonth = now()->addMonth()->toDateString();

        // サーバー側でも必ず「追加可能ユーザー」条件をチェック
        $isEligible = User::query()
            ->where('id', $uid)
            ->where(function ($q) use ($today, $inOneMonth) {
                $q->whereExists(function ($sq) use ($today) {
                    $sq->from('employment_terms as et')
                        ->whereColumn('et.user_id', 'users.id')
                        ->whereDate('et.start_date', '<=', $today)
                        ->where(function ($sq2) use ($today) {
                            $sq2->whereNull('et.end_date')->orWhereDate('et.end_date', '>=', $today);
                        });
                })
                    ->orWhereExists(function ($sq) use ($today, $inOneMonth) {
                        $sq->from('employment_terms as et')
                            ->whereColumn('et.user_id', 'users.id')
                            ->whereDate('et.start_date', '>', $today)
                            ->whereDate('et.start_date', '<=', $inOneMonth);
                    });
            })
            ->exists();

        if (!$isEligible) {
            return back()->withErrors(['user_id' => 'このユーザーは現在新規追加の対象ではありません。'])->withInput();
        }

        // 最小フィールドで作成（他は後で編集可能）
        $schedule = Schedule::create([
            'user_id'         => $uid,
            'label'           => null, // $request->input('label') ?? null,
            'total_minutes'   => 0,
            'effective_start' => now()->toDateString(),
            'effective_end'   => now()->toDateString(),
            'is_active'       => true,
        ]);

        // ★ 検索条件を維持しつつ、user_id は作成ユーザーに固定して一覧へ
        $qs = array_filter([
            'active_on'    => $request->input('active_on'),
            'active_until' => $request->input('active_until'),
            'active'       => $request->input('active'),
            'label'        => $request->input('label'),
            'user_id'      => $uid, // ← 作成ユーザーで固定
        ], fn($v) => $v !== null && $v !== '');

        return redirect()->route('schedules.index', $qs)
            ->with('status', 'Schedule created successfully.');
    }

    public function destroy(Schedule $schedule)
    {
        $viewer = Auth::user();
        if (!$viewer->hasRole(['admin', 'super_admin'])) {
            abort(403, 'You are not authorized to delete schedules.');
        }

        // 検索条件（期間等）はリクエストから受け取り、user_id は付与しない（=全ユーザー）
        $qs = array_filter([
            'active_on'    => request('active_on'),
            'active_until' => request('active_until'),
            'active'       => request('active'),
            'label'        => request('label'),
        ], fn($v) => $v !== null && $v !== '');

        try {
            \DB::transaction(function () use ($schedule) {
                $schedule->delete();
            });

            $redirect = route('schedules.index', $qs);

            if (request()->wantsJson()) {
                // ★ ここでフラッシュを積んでから JSON を返す（クライアントで遷移）
                session()->flash('status', 'Schedule deleted successfully.');
                return response()->json([
                    'ok' => true,
                    'redirect' => $redirect,
                ]);
            }

            return redirect($redirect)->with('status', 'Schedule deleted successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                $msg = 'Cannot delete this schedule because it has related schedule lines. '
                    . 'Please remove those lines first.';

                if (request()->wantsJson()) {
                    // ★ エラー時にリダイレクトする場合は flash＋redirect を返す実装に変えてもOK
                    return response()->json(['ok' => false, 'message' => $msg], 422);
                }

                return back()->withErrors(['delete_error' => $msg]);
            }
            throw $e;
        }
    }
}
