<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
//use Illuminate\Validation\ValidationException;(try catch 用)
use App\Http\Requests\Users\UpdateUserFieldRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;


class UsersController extends Controller
{
    public function show(?User $user = null): View
    {
        // userがnull → 自分のプロフィール（一般ユーザー）
        $targetUser = $user ?? Auth::user();;

        // 権限チェック
        $this->authorize('view', $targetUser);

        // 雇用情報と休職期間をまとめてロード（最新開始日が先頭に来るように）
        $targetUser->load([
            'employmentTerms' => function ($q) {
                $q->with('leavePeriods')
                    ->orderByDesc('start_date');
            },
        ]);

        // 最新の雇用期間を取得（今日の日付でフィルタ）
        // $employment = $targetUser->employmentTerms()->currentAt()->latest('start_date')->first(); // クエリで条件＆並び
        $employment = $targetUser->employmentTerms->first(); // コレクションから最初

        // 休職期間の取得（今日の日付でフィルタ）
        // $activeLeaves = $employment?->activeLeavePeriods() ?? collect();
        $activeLeaves = $employment?->leavePeriods()->coversDate()->get() ?? collect(); //性能的に有利

        return view('user.profile', [
            'user'         => $targetUser,
            'employment'   => $employment,
            'activeLeaves' => $activeLeaves,
        ]);
    }

    /**
     * @property int $id
     * @property string $name
     * @method bool save()
     */
    public function updateField(UpdateUserFieldRequest $request, ?User $user = null): JsonResponse
    {
        $targetUser = $user ?? Auth::user();

        // 権限チェック
        $this->authorize('update', $targetUser);

        $field = $request->input('field');
        $validated = $request->validated(); // バリデーション通過後のデータ

        $allowedFields = ['email', 'phone_number', 'address', 'note'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['error' => '更新できません'], 400);
        }

        $targetUser->$field = $validated['value']; // ※ $validated は ['value' => 入力値] の形
        $targetUser->save();

        return response()->json(['message' => '更新完了']);
    }
}
