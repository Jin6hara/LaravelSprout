<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\RoleChange;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ApprovalRequest;
use App\Notifications\ApprovalRequestedNotification; // 例: 通知クラスのインポート
use Spatie\Permission\Models\Role;



class RoleChangeController extends Controller
{
    public function showRoleChange(User $user): View
    {
        // ユーザーの現在のロールを取得
        $currentRole = $user->getRoleNames()->first();

        // 利用可能なロールを取得
        $availableRoles = ['general', 'admin', 'super_admin'];

        return view('user.roleChange', compact('user', 'currentRole', 'availableRoles'));
    }

    // POST /users/{user}/role-change/apply
    public function apply(Request $request, User $user)
    {
        // 認可（例：本人 or 管理権限のみ申請可）
        $this->authorize('applyRoleChange', $user); // Policy 側で定義推奨

        // バリデーション
        $availableRoles = ['general', 'admin', 'super_admin'];
        $data = $request->validate([
            'role'   => ['required', 'in:' . implode(',', $availableRoles)],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        // 申請者
        $requester = Auth::user();

        // 同一内容の重複 pending を任意で防止
        // RoleChange::where('user_id',$user->id)->where('requested_role',$data['role'])->where('status','pending')->exists()

        DB::transaction(function () use ($user, $requester, $data) {
            // 1) ドメイン側に作成
            $rc = RoleChange::create([
                'user_id'         => $user->id,
                'requested_role'  => $data['role'],
                'reason'          => $data['reason'],
                'requested_by_id' => $requester->id,
                'status'          => 'pending',
            ]);

            // 2) 汎用承認リクエスト（morph）作成
            $title = '権限変更: ' . $rc->targetUser->role_label . ' → ' . self::toJp($data['role']);
            $metadata = [
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'requested_role' => $data['role'],
                'reason' => $data['reason'],
                // 承認フロー定義（例：super_admin ロールの誰か1名承認で可）
                'flow' => [
                    'type' => 'any_of_role',
                    'role' => 'super_admin',
                    'min_approvals' => 1,
                ],
                // 任意で現在ロールも入れておく
                'current_role' => $user->getRoleNames()->first(),
            ];

            $rc->approvalRequest()->create([
                'title'           => $title,
                'requested_by_id' => $requester->id,
                'current_state'   => 'pending',
                'metadata'        => $metadata,
            ]);

            // 3) 通知（例：super_admin 全員に通知）
            $this->notifyApprovers($data['role'], $rc);
        });

        return back()->with('toast', '権限変更の申請を受け付けました。');
    }

    // 表示名に使う簡易マップ（Bladeの getRoleLabelAttribute に合わせる）
    private static function toJp(string $role): string
    {
        return [
            'general'     => '一般',
            'admin'       => '管理者',
            'super_admin' => 'スーパー管理者',
        ][$role] ?? $role;
    }

    private function notifyApprovers(string $requestedRole, RoleChange $rc): void
    {
        // 例: super_admin へ通知
        $roleNeedingApproval = 'super_admin';

        $approvers = Role::findByName($roleNeedingApproval)
            ->users()
            ->get();

        // 任意の通知クラスを発火（実装例下）
        foreach ($approvers as $user) {
            $user->notify(new ApprovalRequestedNotification($rc->approvalRequest));
        }
    }
}
