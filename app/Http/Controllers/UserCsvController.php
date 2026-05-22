<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\District;
use App\Models\EmploymentTerm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserCsvController extends Controller
{
    private const EXPORT_HEADERS = [
        'id', 'family_name', 'first_name', 'middle_name', 'name_in_kana',
        'email', 'gender', 'note', 'employee_code', 'address', 'phone_number',
        'district_name', 'department_name', 'role',
        'employment_start_date', 'employment_end_date',
        'employment_type_name', 'employment_type_code', 'employment_note',
        'created_at', 'updated_at',
    ];

    private const IMPORT_REQUIRED_COLS = [
        'family_name', 'first_name', 'employee_code', 'email',
        'district_name', 'department_name', 'role',
    ];

    /**
     * ユーザー CSV 管理画面
     * GET /csv/users — インポート・エクスポートフォームを表示
     */
    public function show()
    {
        $this->authorize('viewAny', User::class);

        return view('csv.user_csv');
    }

    /**
     * ユーザーデータを CSV でエクスポート
     * GET /csv/users/export — district・department・role・雇用期間を含む全ユーザーをストリームダウンロード
     */
    public function export()
    {
        $this->authorize('viewAny', User::class);

        $users = User::with([
            'district',
            'department',
            'roles',
            'employmentTerms',
        ])->orderBy('id')->get();

        $filename = 'users_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($users) {
            $fp = fopen('php://output', 'w');
            fputcsv($fp, self::EXPORT_HEADERS);

            foreach ($users as $user) {
                // end_date が null のものを優先、なければ start_date が最新のもの
                $term = $user->employmentTerms->firstWhere('end_date', null)
                    ?? $user->employmentTerms->sortByDesc('start_date')->first();

                fputcsv($fp, [
                    $user->id,
                    $user->family_name,
                    $user->first_name,
                    $user->middle_name,
                    $user->name_in_kana,
                    $user->email,
                    $user->gender,
                    $user->note,
                    $user->employee_code,
                    $user->address,
                    $user->phone_number,
                    $user->district?->name,
                    $user->department?->name,
                    $user->getRoleNames()->first(),
                    $term?->start_date?->toDateString(),
                    $term?->end_date?->toDateString(),
                    $term?->type_name,
                    $term?->type_code,
                    $term?->note,
                    $user->created_at,
                    $user->updated_at,
                ]);
            }
            fclose($fp);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * CSV からユーザーデータをインポート
     * POST /csv/users/import — BOM除去・必須カラム検証・全行バリデーションの後、id 一致で更新・なければ新規作成。雇用期間も同時処理
     */
    public function import(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $fp   = fopen($path, 'r');
        if (!$fp) {
            return back()->withErrors(['csv_file' => 'ファイルを開けません。']);
        }

        $rawHeader = fgetcsv($fp);
        if (!$rawHeader) {
            fclose($fp);
            return back()->withErrors(['csv_file' => 'ヘッダー行がありません。']);
        }

        // BOM除去 & trim
        $header = array_map(function (string $h): string {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
        }, $rawHeader);

        // 必須カラム確認
        $missing = array_diff(self::IMPORT_REQUIRED_COLS, $header);
        if (!empty($missing)) {
            fclose($fp);
            $detected = implode(', ', array_values(array_filter($header, fn($h) => $h !== '')));
            return back()->withErrors([
                'csv_file' => '必須カラムが不足しています: ' . implode(', ', $missing)
                    . '　／　検出されたカラム(' . count($header) . '列): ' . ($detected ?: 'なし'),
            ]);
        }

        $rows        = [];
        $parseErrors = [];
        $rowNum      = 1;

        while (($row = fgetcsv($fp)) !== false) {
            $rowNum++;
            if (count($row) !== count($header)) {
                $parseErrors[] = "{$rowNum}行目: 列数が一致しません（期待: " . count($header) . "、実際: " . count($row) . "）";
                continue;
            }
            $data = array_combine($header, $row);
            foreach ($data as $k => $v) {
                $data[$k] = $this->normalizeCsvValue($v);
            }
            // Excel が先頭ゼロを落とすため、6桁未満は左ゼロ埋めで補完する
            if (isset($data['employee_code']) && $data['employee_code'] !== null) {
                $data['employee_code'] = str_pad($data['employee_code'], 6, '0', STR_PAD_LEFT);
            }
            $rows[] = ['row' => $rowNum, 'data' => $data];
        }
        fclose($fp);

        if (!empty($parseErrors)) {
            return back()->with('toast_errors', $parseErrors);
        }

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'データ行がありません。']);
        }

        // ====== 全行バリデーション ======
        $validationErrors = [];

        // マスターデータをキャッシュ（N+1防止）
        $districtMap   = District::pluck('id', 'name')->all();
        $departmentMap = Department::pluck('id', 'name')->all();
        $roleMap       = Role::where('guard_name', 'web')->pluck('name', 'name')->all();
        $existingEmails = User::pluck('id', 'email')->all();
        $existingCodes  = User::pluck('id', 'employee_code')->all();

        foreach ($rows as ['row' => $rowNum, 'data' => $data]) {
            $v = Validator::make($data, [
                'family_name'           => ['required', 'string', 'max:255'],
                'first_name'            => ['required', 'string', 'max:255'],
                'middle_name'           => ['nullable', 'string', 'max:255'],
                'name_in_kana'          => ['nullable', 'string', 'max:255'],
                'email'                 => ['required', 'email', 'max:255'],
                'employee_code'         => ['required', 'string', 'size:6'],
                'gender'                => ['nullable', 'in:male,female,other,unknown'],
                'note'                  => ['nullable', 'string'],
                'address'               => ['nullable', 'string', 'max:255'],
                'phone_number'          => ['nullable', 'string', 'max:255'],
                'district_name'         => ['required', 'string'],
                'department_name'       => ['required', 'string'],
                'employment_start_date' => ['nullable', 'date'],
                'employment_end_date'   => ['nullable', 'date'],
                // start_date がある場合は type_name / type_code も必須（DB NOT NULL 制約）
                'employment_type_name'  => ['required_with:employment_start_date', 'nullable', 'string', 'max:255'],
                'employment_type_code'  => ['required_with:employment_start_date', 'nullable', 'string', 'max:255'],
                'employment_note'       => ['nullable', 'string', 'max:255'],
            ]);

            if ($v->fails()) {
                foreach ($v->errors()->all() as $msg) {
                    $validationErrors[] = "{$rowNum}行目: {$msg}";
                }
                continue;
            }

            $id = (isset($data['id']) && is_numeric($data['id'])) ? (int) $data['id'] : null;

            // district 解決
            if (!array_key_exists($data['district_name'], $districtMap)) {
                $validationErrors[] = "{$rowNum}行目: district_name「{$data['district_name']}」が districts テーブルに見つかりません。";
            }

            // department 解決
            if (!array_key_exists($data['department_name'], $departmentMap)) {
                $validationErrors[] = "{$rowNum}行目: department_name「{$data['department_name']}」が departments テーブルに見つかりません。";
            }

            // role 解決（空なら general）
            $roleName = $data['role'] ?? null;
            if ($roleName === null) {
                $roleName = 'general';
            }
            if (!array_key_exists($roleName, $roleMap)) {
                $validationErrors[] = "{$rowNum}行目: role「{$roleName}」が roles テーブルに存在しません。";
            }

            // email 重複チェック（別ユーザー）
            $emailOwner = $existingEmails[$data['email']] ?? null;
            if ($emailOwner !== null && $emailOwner !== $id) {
                $validationErrors[] = "{$rowNum}行目: email「{$data['email']}」は既に別のユーザーが使用しています。";
            }

            // employee_code 重複チェック（別ユーザー）
            $codeOwner = $existingCodes[$data['employee_code']] ?? null;
            if ($codeOwner !== null && $codeOwner !== $id) {
                $validationErrors[] = "{$rowNum}行目: employee_code「{$data['employee_code']}」は既に別のユーザーが使用しています。";
            }
        }

        if (!empty($validationErrors)) {
            return back()->with('toast_errors', $validationErrors);
        }

        // ====== DB保存（トランザクション）======
        $created             = 0;
        $updated             = 0;
        $employmentProcessed = 0;

        DB::transaction(function () use (
            $rows, $districtMap, $departmentMap,
            &$created, &$updated, &$employmentProcessed
        ) {
            foreach ($rows as ['data' => $data]) {
                $id = (isset($data['id']) && is_numeric($data['id'])) ? (int) $data['id'] : null;

                // role 確定（空なら general）
                $roleName = $data['role'] ?? null;
                if ($roleName === null) {
                    $roleName = 'general';
                }

                $payload = [
                    'family_name'   => $data['family_name'],
                    'first_name'    => $data['first_name'],
                    'middle_name'   => $data['middle_name'] ?? null,
                    'name_in_kana'  => $data['name_in_kana'] ?? null,
                    'email'         => $data['email'],
                    'employee_code' => $data['employee_code'],
                    'gender'        => $data['gender'] ?? 'unknown',
                    'note'          => $data['note'] ?? null,
                    'address'       => $data['address'] ?? null,
                    'phone_number'  => $data['phone_number'] ?? null,
                    'district_id'   => $districtMap[$data['district_name']],
                    'department_id' => $departmentMap[$data['department_name']],
                ];

                $user = $id !== null ? User::find($id) : null;

                if ($user) {
                    $user->update($payload);
                    $updated++;
                } else {
                    // 新規作成時のみ安全な初期パスワードを自動設定
                    $payload['password'] = Str::random(16);
                    $user = User::create($payload);
                    $created++;
                }

                // role 付与（既存を置き換え）
                $user->syncRoles([$roleName]);

                // employment_terms: start_date があれば作成/更新
                $startDate = $data['employment_start_date'] ?? null;
                if ($startDate !== null) {
                    $term = EmploymentTerm::firstOrNew([
                        'user_id'    => $user->id,
                        'start_date' => $startDate,
                    ]);
                    $term->end_date  = $data['employment_end_date'] ?? null;
                    $term->type_name = $data['employment_type_name'] ?? null;
                    $term->type_code = $data['employment_type_code'] ?? null;
                    $term->note      = $data['employment_note'] ?? null;
                    $term->save();
                    $employmentProcessed++;
                }
            }
        });

        return back()->with(
            'toast',
            "インポート完了：作成 {$created} 件、更新 {$updated} 件、雇用期間処理 {$employmentProcessed} 件"
        );
    }

    /**
     * 空白文字・空文字列を null に正規化する。
     */
    private function normalizeCsvValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
