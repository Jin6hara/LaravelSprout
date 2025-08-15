<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
//use Illuminate\Validation\ValidationException;(try catch 用)
use App\Http\Requests\UpdateUserFieldRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;


class UsersController extends Controller
{
    public function showProfile(?User $user = null): View
    {
        $currentUser = Auth::user();

        // userがnull → 自分のプロフィール（一般ユーザー）
        $targetUser = $user ?? $currentUser;

        // 権限チェック
        $this->authorize('view', $targetUser);

        // 雇用情報と休職期間をまとめてロード（最新開始日が先頭に来るように）
        $targetUser->load([
            'employmentTerms' => function ($q) {
                $q->with('leavePeriods')
                    ->orderByDesc('start_date');
            },
        ]);

        // 最新の雇用情報 1 件（なければ null）
        $employment = $targetUser->employmentTerms->first();

        // 「現在休職中」の期間だけ抽出（start_date <= today <= end_date or end_date is null）
        $activeLeaves = $employment?->activeLeavePeriods() ?? collect();

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
    public function updateField(UpdateUserFieldRequest $request): JsonResponse
    {
        $targetUser = Auth::user();

        // 権限チェック
        $this->authorize('update', $targetUser);

        $field = $request->input('field');
        $validated = $request->validated(); // バリデーション通過後のデータ

        $allowedFields = ['email', 'phone_number', 'address', 'self_introduction'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['error' => '更新できません'], 400);
        }

        $targetUser->$field = $validated['value']; // ※ $validated は ['value' => 入力値] の形
        $targetUser->save();

        return response()->json(['message' => '更新完了']);
    }
}
