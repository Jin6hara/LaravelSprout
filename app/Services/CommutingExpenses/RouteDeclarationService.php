<?php

namespace App\Services\CommutingExpenses;

use App\Models\User;
use App\Models\RouteDeclaration;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class RouteDeclarationService
{
    /** Sun→Sat の固定順 */
    public const DOW_ORDER = "CASE dow WHEN 'Sun' THEN 0 WHEN 'Mon' THEN 1 WHEN 'Tue' THEN 2 WHEN 'Wed' THEN 3 WHEN 'Thu' THEN 4 WHEN 'Fri' THEN 5 WHEN 'Sat' THEN 6 ELSE 99 END";

    /**
     * 指定ユーザーの RouteDeclaration をすべて取得（effective_date 降順）
     * details は Sun→Sat 順、user.commuterPasses を同梱
     */
    public function allForUser(User $user): Collection
    {
        return RouteDeclaration::query()
            ->where('user_id', $user->id)
            ->with([
                'user.commuterPasses',
                // ★ Builder/Relation どちらでも動くように型ヒントを付けない
                'details' => function ($q) {
                    $q->orderByRaw(self::DOW_ORDER);
                },
            ])
            ->orderByDesc('effective_date')
            ->get();
    }

    /**
     * 指定日時点（<= $end）での最新 RouteDeclaration を1件取得
     * details は Sun→Sat 順
     */
    public function latestUntil(User $user, Carbon $end): ?RouteDeclaration
    {
        return RouteDeclaration::query()
            ->where('user_id', $user->id)
            ->whereDate('effective_date', '<=', $end->toDateString())
            ->with([
                'user',
                // ★ 同上：型ヒントを付けない
                'details' => function ($q) {
                    $q->orderByRaw(self::DOW_ORDER);
                },
            ])
            ->orderByDesc('effective_date')
            ->first();
    }
}
