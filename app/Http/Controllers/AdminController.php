<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Requests\AdminUpdateUserFieldRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;

class AdminController extends Controller
{
    /**
     * ダッシュボード（初期表示：全件→検索UIも表示）
     */
    public function dashboard(Request $request)
    {
        // 初期表示では検索語なし・対象は既定3つ
        $word   = trim((string) $request->query('search_word', ''));
        $fields = collect((array) $request->query('fields', ['employee_code', 'name', 'phone_number']))
            ->intersect(['employee_code', 'name', 'phone_number'])
            ->take(3);

        // 検索語が空なら全件、入っていれば対象フィールドで部分一致
        $query = User::query()
            ->when(filled($word), function ($q) use ($word, $fields) {
                $q->where(function ($w) use ($word, $fields) {
                    $fields->values()->each(function ($field, $i) use ($w, $word) {
                        // 先頭だけ where、それ以降は orWhere
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $w->{$method}($field, 'like', "%{$word}%");
                    });
                });
            });

        $users = $query
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.dashboard', compact('users', 'word', 'fields'));
    }

    public function showForm()
    {
        return view('auth.register');
    }

    public function register(UserRequest $request)
    {

        User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'employee_code'     => $request->employee_code,
            'password'          => Hash::make($request->password),
            'gender'            => $request->gender,
            'role'              => 'general', //登録時の誤設定を防ぐ目的で、権限は後から変更できる仕様とする。
            'profile_picture'   => null,
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
        $this->authorize('update', $targetUser);

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

    /**
     * 検索（GET /admin/search）
     * 実質は dashboard と同じロジック。分けたいという要望に合わせてルートだけ分離。
     */
    public function search(Request $request)
    {
        // dashboard() をそのまま再利用でもOK。ここではコピペで明示。
        $word   = trim((string) $request->query('search_word', ''));
        $fields = collect((array) $request->query('fields', ['employee_code','name','phone_number']))
            ->intersect(['employee_code','name','phone_number'])
            ->take(3);

        $query = User::query()
            ->when(filled($word), function ($q) use ($word, $fields) {
                $q->where(function ($w) use ($word, $fields) {
                    $fields->values()->each(function ($field, $i) use ($w, $word) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $w->{$method}($field, 'like', "%{$word}%");
                    });
                });
            });

        $users = $query
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.dashboard', compact('users','word','fields'));
    }
}
