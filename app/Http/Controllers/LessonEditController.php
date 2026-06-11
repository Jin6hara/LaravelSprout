<?php
/**
 * レッスンマスタの一覧表示・検索・新規登録・編集・削除を担うコントローラ。
 */
namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonEditController extends Controller
{
    private const PER_PAGE_OPTIONS = [60, 120, 180];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lesson::class);

        $search = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 60);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 60;
        }

        $lessons = Lesson::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereLikeInsensitive('lesson_name', $search)
                        ->orWhereLikeInsensitive('lesson_code', $search)
                        ->orWhereLikeInsensitive('note', $search)
                        ->orWhereLikeInsensitive('ps_unique_lesson_code', $search)
                        ->orWhereLikeInsensitive('fm_lesson_code', $search);

                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('data.lesson_edit', [
            'lessons' => $lessons,
            'search' => $search,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Lesson::class);

        Lesson::create($this->validatedLessonData($request));

        return redirect()
            ->route('data.lessons.index')
            ->with('toast', 'Lesson created.');
    }

    public function update(Request $request, Lesson $lesson)
    {
        $this->authorize('update', $lesson);

        $lesson->update($this->validatedLessonData($request));

        return back()->with('toast', "Lesson #{$lesson->id} updated.");
    }

    public function destroy(Lesson $lesson)
    {
        $this->authorize('delete', $lesson);

        if ($lesson->scheduleDetails()->exists() || DB::table('event_details')->where('lesson_id', $lesson->id)->exists()) {
            return back()->with('toast_errors', ["Lesson #{$lesson->id} is used by schedules and cannot be deleted."]);
        }

        $lessonId = $lesson->id;
        $lesson->delete();

        return back()->with('toast', "Lesson #{$lessonId} deleted.");
    }

    private function validatedLessonData(Request $request): array
    {
        $data = $request->validate([
            'lesson_name' => ['nullable', 'string', 'max:255'],
            'lesson_code' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'lesson_minute' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'lesson_type' => ['nullable', 'string', 'max:255'],
            'ps_unique_lesson_code' => ['nullable', 'string', 'max:255'],
            'fm_lesson_code' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $data[$key] = $value === '' ? null : $value;
            }
        }

        return $data;
    }
}
