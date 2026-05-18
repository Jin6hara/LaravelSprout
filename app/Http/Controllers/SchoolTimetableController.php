<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Lesson;
use App\Models\ScheduleLine;
use App\Models\School;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SchoolTimetableController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    public function index(Request $request)
    {
        $schoolName = trim((string)$request->input('school', ''));
        $dow = $request->has('dow') && $request->input('dow') !== ''
            ? (int)$request->input('dow')
            : null;

        $userOptions = $this->scopeService->targetUserQuery()
            ->orderBy('family_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'family_name', 'employee_code'])
            ->map(function ($u) {
                $label = trim($u->family_name . ' ' . ($u->first_name ?? ''));
                if (!empty($u->employee_code)) {
                    $label .= " [{$u->employee_code}]";
                }
                return ['id' => $u->id, 'label' => $label];
            })
            ->values();

        $dowOptions = [
            0 => '日', 1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土',
        ];

        $districtId = $this->scopeService->currentDistrictId();
        $usedDeptIds = ScheduleLine::where('district_id', $districtId)
            ->distinct()
            ->pluck('department_id')
            ->filter()
            ->values();
        $departmentOptions = Department::whereIn('id', $usedDeptIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();

        $props = [
            'initialSchool'      => $schoolName,
            'initialDow'         => $dow,
            'userOptions'        => $userOptions,
            'dowOptions'         => $dowOptions,
            'departmentOptions'  => $departmentOptions,
            'csrfToken'          => csrf_token(),
            'apiLinesUrl'        => route('school_timetable.lines'),
            'schoolsUrl'         => route('school_timetable.schools'),
            'lessonsUrl'         => route('school_timetable.lessons'),
            'storeLinesUrl'      => route('schedule_lines.store'),
            'bulkUpdateLinesUrl' => route('schedule_lines.bulk_update'),
            'lessonByCodeUrl'    => url('/lessons/by-code'),
        ];

        return view('schedule.schoolTimetable', compact('props'));
    }

    // API: school_name（+ 任意で dow）で絞った schedule_lines + details を JSON で返す
    public function lines(Request $request)
    {
        $schoolName = trim((string)$request->input('school', ''));
        $activeOn   = $request->input('active_on', now()->toDateString());
        $dow = $request->has('dow') && $request->input('dow') !== ''
            ? (int)$request->input('dow')
            : null;

        if ($schoolName === '') {
            return response()->json(['ok' => false, 'message' => 'school は必須です'], 422);
        }

        $activeDate = Carbon::parse($activeOn)->toDateString();

        $query = ScheduleLine::query()
            ->with([
                'user:id,first_name,family_name,employee_code',
                'details' => function ($q) use ($activeDate) {
                    $q->with(['lesson:id,lesson_name,lesson_code,note,lesson_minute'])
                      ->whereDate('effective_start', '<=', $activeDate)
                      ->where(function ($q2) use ($activeDate) {
                          $q2->whereDate('effective_end', '>=', $activeDate)
                             ->orWhereNull('effective_end');
                      })
                      ->orderBy('start_time');
                },
            ])
            ->where('district_id', $this->scopeService->currentDistrictId())
            ->whereLikeInsensitive('school_name', $schoolName)
            ->orderBy('dow')
            ->orderBy('start_time');

        if (!is_null($dow)) {
            $query->where('dow', $dow);
        }

        if ($activeOn) {
            $d = Carbon::parse($activeOn)->toDateString();
            $query->whereDate('effective_start', '<=', $d)
                  ->where(function ($q) use ($d) {
                      $q->whereDate('effective_end', '>=', $d)
                        ->orWhereNull('effective_end');
                  });
        }

        $lines = $query->get()->map(function ($line) {
            $user     = $line->user;
            $userName = $user
                ? trim(($user->family_name ?? '') . ' ' . ($user->first_name ?? ''))
                : null;

            return [
                'id'              => $line->id,
                'school_name'     => $line->school_name,
                'department_id'   => $line->department_id,
                'user_id'         => $line->user_id,
                'user_name'       => $userName,
                'dow'             => $line->dow,
                'start_time'      => substr((string)$line->start_time, 0, 5),
                'end_time'        => substr((string)$line->end_time, 0, 5),
                'total_minutes'   => $line->total_minutes,
                'effective_start' => optional($line->effective_start)->toDateString(),
                'effective_end'   => optional($line->effective_end)->toDateString(),
                'handover_memo'   => $line->handover_memo,
                'details'         => $line->details->map(function ($d) {
                    $lesson = $d->lesson;
                    return [
                        'id'              => $d->id,
                        'start_time'      => substr((string)$d->start_time, 0, 5),
                        'lesson_id'       => $d->lesson_id,
                        'lesson_code'     => $lesson->lesson_code ?? '',
                        'lesson_name'     => $lesson->lesson_name ?? '',
                        'lesson_minute'   => $lesson->lesson_minute ?? 0,
                        'note'            => $lesson->note ?? '',
                        'detail_note'     => $d->note ?? '',
                        'effective_start' => optional($d->effective_start)->toDateString(),
                        'effective_end'   => optional($d->effective_end)->toDateString(),
                    ];
                })->values(),
            ];
        });

        return response()->json(['ok' => true, 'lines' => $lines]);
    }

    // API: school_code / school_name 一覧（datalist 用）
    public function schools()
    {
        $schools = School::query()
            ->where('is_active', true)
            ->orderBy('school_name')
            ->get(['school_code', 'school_name']);

        return response()->json(['ok' => true, 'schools' => $schools]);
    }

    // API: lesson 一覧（datalist 用）
    public function lessonList()
    {
        $lessons = Lesson::query()
            ->orderBy('lesson_code')
            ->get(['id', 'lesson_code', 'ps_unique_lesson_code']);

        return response()->json(['ok' => true, 'lessons' => $lessons]);
    }
}
