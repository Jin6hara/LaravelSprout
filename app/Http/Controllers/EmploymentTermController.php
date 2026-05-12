<?php

namespace App\Http\Controllers;

use App\Models\EmploymentTerm;
use App\Models\User;
use App\Http\Requests\EmploymentTermRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmploymentTermController extends Controller
{
    /**
     * 対象ユーザーの雇用履歴一覧を表示（N+1 を避けるため leavePeriods を eager load）
     */
    public function details(User $user): View
    {
        $employmentTerms = $user->employmentTerms()
            ->with('leavePeriods')
            ->orderByDesc('start_date')
            ->get();

        return view('user.profile.employment.details', compact('user', 'employmentTerms'));
    }

    /**
     * 雇用期間の編集フォームを表示
     */
    public function edit(EmploymentTerm $employmentTerm): View
    {
        $user = $employmentTerm->user;
        return view('user.profile.employment.edit', compact('employmentTerm', 'user'));
    }

    /**
     * 雇用期間を更新して詳細画面へリダイレクト
     */
    public function update(EmploymentTermRequest $request, EmploymentTerm $employmentTerm): RedirectResponse
    {
        $data = $request->validated();

        // 簡易重複チェック：同一ユーザーの他の employment_term と期間が重複しないか確認
        // TODO: より厳密な重複検知（境界値・隣接期間の扱い）が必要な場合はこのチェックを拡張してください
        $newEnd = $data['end_date'] ?? '9999-12-31'; // null（無期限）は遠い未来として扱う
        $overlap = EmploymentTerm::where('user_id', $employmentTerm->user_id)
            ->where('id', '!=', $employmentTerm->id)
            ->where('start_date', '<=', $newEnd)
            ->where(function ($q) use ($data) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $data['start_date']);
            })
            ->exists();

        if ($overlap) {
            return back()
                ->withErrors(['start_date' => '他の雇用期間と重複しています。期間を確認してください。'])
                ->withInput();
        }

        $employmentTerm->update($data);

        return redirect()
            ->route('employment_terms.details', $employmentTerm->user)
            ->with('status', '雇用情報を更新しました。');
    }
}
