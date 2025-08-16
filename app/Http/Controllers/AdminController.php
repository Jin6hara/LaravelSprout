<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Requests\AdminUpdateUserFieldRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * ダッシュボード（初期表示：全件→検索UIも表示）
     */
    public function dashboard(Request $request): View
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
            ->when(filled($word), function (Builder $q) use ($word, $fields) {
                $q->where(function (Builder $w) use ($word, $fields) {
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

    /**
     * 登録処理（管理者用）
     * Handle user registration by validating input and creating user and employment terms records.
     */
    public function register(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, &$user) {

            // user テーブルへの登録
            $user = User::create([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'employee_code'     => $data['employee_code'],
                'password'          => Hash::make($data['password']),
                'gender'            => $data['gender'],
                'role'              => 'general', //登録時の誤設定を防ぐ目的で、権限は後から変更できる仕様とする。
                'profile_picture'   => null,
                'self_introduction' => 'こんにちは、' . $data['name'] . 'です。',
            ]);
            // employment_terms テーブルへの登録
            $user->employmentTerms()->create([
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'] ?? null, // null なら在籍中
                'note'       => $data['note'] ?? null,
            ]);
        });

        return redirect()->route('admin.dashboard')->with("status", "登録成功");
    }

    /**
     * 検索（GET /admin/search）
     * 実質は dashboard と100％同じロジック。URL表示を分けるため、ルートだけ分離。
     */
    public function search(Request $request): View
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
            ->when(filled($word), function (Builder $q) use ($word, $fields) {
                $q->where(function (Builder $w) use ($word, $fields) {
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
