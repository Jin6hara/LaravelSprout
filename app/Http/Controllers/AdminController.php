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
        // 検索対象フィールドのホワイトリスト
        $allowedFields = ['employee_code', 'name', 'phone_number'];

        // 入力取得
        $word   = trim((string) $request->query('search_word', ''));
        $fields = collect((array) $request->query('fields', $allowedFields))
            ->intersect($allowedFields)
            ->take(3);

        // 万が一 0 件になったら全項目にフォールバック（?fields[]= などの変形入力対策）
        if ($fields->isEmpty()) {
            $fields = collect($allowedFields);
        }

        // 検索クエリ
        $query = User::query()
            ->when(filled($word), function ($q) use ($word, $fields) {
                $q->where(function ($w) use ($word, $fields) {
                    $fields->values()->each(function ($field, $i) use ($w, $word) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $w->{$method}($field, 'like', "%{$word}%");
                    });
                });
            });

        // 並び替え（既定: updated_at desc）
        $sort = $request->query('sort', 'updated_at');
        $dir  = strtolower($request->query('dir', 'desc'));

        $allowedSorts = ['updated_at', 'employee_code', 'name'];
        $allowedDirs  = ['asc', 'desc'];

        if (!in_array($sort, $allowedSorts, true)) $sort = 'updated_at';
        if (!in_array($dir,  $allowedDirs,  true)) $dir  = 'desc';

        // employee_code が 5桁固定ならそのままでOK。可変長なら数値順にキャストも可（下のコメント参照）
        $query->orderBy($sort, $dir);

        $users = $query->paginate(10)->withQueryString();

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
     * 実質は dashboard と100％同じロジック。URL表示を分けるため、ルートだけ分離。
     */
    public function search(Request $request)
    {
        $allowedFields = ['employee_code', 'name', 'phone_number'];

        $word   = trim((string) $request->query('search_word', ''));
        $fields = collect((array) $request->query('fields', $allowedFields))
            ->intersect($allowedFields)
            ->take(3);

        if ($fields->isEmpty()) {
            $fields = collect($allowedFields);
        }

        $query = User::query()
            ->when(filled($word), function ($q) use ($word, $fields) {
                $q->where(function ($w) use ($word, $fields) {
                    $fields->values()->each(function ($field, $i) use ($w, $word) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $w->{$method}($field, 'like', "%{$word}%");
                    });
                });
            });

        $sort = $request->query('sort', 'updated_at');
        $dir  = strtolower($request->query('dir', 'desc'));

        $allowedSorts = ['updated_at', 'employee_code', 'name'];
        $allowedDirs  = ['asc', 'desc'];

        if (!in_array($sort, $allowedSorts, true)) $sort = 'updated_at';
        if (!in_array($dir,  $allowedDirs,  true)) $dir  = 'desc';

        $query->orderBy($sort, $dir);

        $users = $query->paginate(10)->withQueryString();

        return view('admin.dashboard', compact('users', 'word', 'fields'));
    }
}
