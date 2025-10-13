<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventApiController extends Controller
{
    public function store(Request $req)
    {
        $data = $this->validated($req);

        // total_minutes を安全に再計算
        $data['total_minutes'] = $this->calcMinutes($data['start_time'] ?? null, $data['end_time'] ?? null);

        $event = Event::create($data);

        return response()->json(['ok'=>true, 'id'=>$event->id]);
    }

    public function update(Request $req, Event $event)
    {
        $data = $this->validated($req, updating:true);
        $data['total_minutes'] = $this->calcMinutes($data['start_time'] ?? null, $data['end_time'] ?? null);

        $event->update($data);

        return response()->json(['ok'=>true]);
    }

    public function destroy(Request $req, Event $event)
    {
        $event->delete();
        return response()->json(['ok'=>true]);
    }

    private function validated(Request $req, bool $updating=false): array
    {
        // id はURLパラメータで決まるので未使用
        return $req->validate([
            'event_date'        => ['required','date'],
            'original_user_id'  => ['nullable','integer','exists:users,id'],
            'Leave_type'        => ['nullable','string'],
            'sub'               => ['required', Rule::in(['none_required','required','other'])],
            'title'             => ['nullable','string','max:255'],
            'school_name'       => ['nullable','string','max:255'],
            'start_time'        => ['nullable','date_format:H:i'],
            'end_time'          => ['nullable','date_format:H:i'],
            'total_minutes'     => ['nullable','integer','min:0'], // 実際は再計算で上書き
            'Lesson'            => ['nullable','string'],
            'assigned_user_id'  => ['nullable','integer','exists:users,id'],
            'status'            => ['required', Rule::in(['pending','fixed','filled','in_process'])],
            'type'              => ['required', Rule::in(['regular_time','overtime','schedule_change','special'])],
            'notes'             => ['nullable','string'],
        ]);
    }

    private function calcMinutes(?string $start, ?string $end): ?int
    {
        if (!$start || !$end) return null;
        try {
            $s = Carbon::createFromFormat('H:i', $start);
            $e = Carbon::createFromFormat('H:i', $end);
            if ($e->lessThanOrEqualTo($s)) {
                // 終了が開始以下なら翌日扱い（深夜跨ぎ）
                $e->addDay();
            }
            return $s->diffInMinutes($e);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
