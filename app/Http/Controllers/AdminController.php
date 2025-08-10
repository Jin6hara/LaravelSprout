<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Requests\AdminUpdateUserFieldRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard');
    }

    public function showForm()
    {
        return view('auth.register');
    }

    public function register(UserRequest $request)
    {

        $defaultPictures = config('user.default_profile_pictures');

        User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'employee_code'     => $request->employee_code,
            'password'          => Hash::make($request->password),
            'gender'            => $request->gender,
            'role'              => 'general',//登録時の誤設定を防ぐ目的で、権限は後から変更できる仕様とする。
            'profile_picture'   => $defaultPictures[$request->gender],
            'self_introduction' => 'こんにちは、' . $request->name . 'です。',
        ]);

        return redirect()->route('admin.dashboard')->with("status", "登録成功");
    }

    /**
     * @property int $id
     * @property string $name
     * @method bool save()
     */
    public function updateField(AdminUpdateUserFieldRequest $request, User $user)
    {

        $targetUser = $user;

        // 権限チェック
        $this->authorize('view', $targetUser);

        $field = $request->input('field');
        $validated = $request->validated(); // バリデーション通過後のデータ

        $allowedFields = ['employee_code', 'name', 'gender', 'email', 'phone_number', 'address', 'self_introduction'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['error' => '更新できません'], 400);
        }

        $targetUser->$field = $validated['value']; // ※ $validated は ['value' => 入力値] の形
        $targetUser->save();

        return response()->json(['message' => '更新完了']);
    }
}
