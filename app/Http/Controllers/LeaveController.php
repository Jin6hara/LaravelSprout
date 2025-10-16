<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\Leave;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function create()
    {
        $user = auth()->user();
        return view('leaves.create', [
            'defaultUserId' => $user->id,
        ]);
    }

    public function store(StoreLeaveRequest $request)
    {
        // 承認フローがある場合は pending から始めるなど調整してください
        $leave = Leave::create([
            'user_id'     => (int)$request->input('user_id'),
            'start_date'  => $request->date('start_date'),
            'end_date'    => $request->input('end_date') ? $request->date('end_date') : null,
            'kind'        => $request->input('kind'),
            'excused'     => $request->input('excused', 'unexcused'),
            'special_type' => $request->input('special_type'),
            'reason'      => $request->input('reason'),
            'time_start'  => $request->input('time_start'),
            'time_end'    => $request->input('time_end'),
            'status'      => $request->input('status', 'approved'),
            'approved_by' => auth()->id(), // 簡易に自分で承認した体
        ]);

        // 作成日のカレンダー画面に遷移
        $date = optional($leave->start_date)->format('Y-m-d');

        // Observer が自動でスナップショット生成
        return redirect()->to(route('calendar.edit') . '?event_date=' . urlencode($date))
            ->with('status', '欠席登録成功。通常シフトある場合はEventが作成されます。');
    }


    public function cancel(Leave $leave)
    {
        $this->authorize('cancel', $leave);

        DB::transaction(function () use ($leave) {
            $wasApproved = $leave->status === 'approved';

            $leave->update(['status' => 'cancelled']);

            if ($wasApproved) {
                app(LeaveBalanceService::class)->revert($leave);
            }
        });

        return back()->with('success', '申請を取り消しました。');
    }
}
