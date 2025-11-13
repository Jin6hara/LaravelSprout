<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        $limit = (int) min(max((int) $r->query('limit', 20), 1), 50);

        $users = User::query()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(
                    fn($w) =>
                    $w->where('first_name', 'like', "%{$q}%")
                        ->orWhere('family_name', 'like', "%{$q}%")
                        ->orWhere('employee_code', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                );
            })
            ->orderBy('family_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get(['id', 'first_name', 'family_name', 'employee_code', 'email']);

        return response()->json($users);
    }
}
