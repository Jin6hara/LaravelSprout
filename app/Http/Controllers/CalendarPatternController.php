<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRestPattern;
use Illuminate\Http\Request;

class CalendarPatternController extends Controller
{
    public function apiHistory(User $user)
    {
        $rows = UserRestPattern::where('user_id', $user->id)
            ->with('pattern:id,name,code')
            ->orderBy('start_date')
            ->orderByRaw('end_date IS NULL, end_date')
            ->get();
        return response()->json($rows);
    }

    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'rest_pattern_id' => 'required|exists:rest_patterns,id',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
        ]);
        $data['user_id'] = $user->id;
        $row = UserRestPattern::create($data);
        $row->load('pattern:id,name,code');
        return response()->json($row, 201);
    }

    public function update(Request $request, User $user, UserRestPattern $assignment)
    {
        abort_if($assignment->user_id !== $user->id, 403);
        $data = $request->validate([
            'rest_pattern_id' => 'required|exists:rest_patterns,id',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after_or_equal:start_date',
        ]);
        $assignment->update($data);
        $assignment->load('pattern:id,name,code');
        return response()->json($assignment);
    }

    public function destroy(User $user, UserRestPattern $assignment)
    {
        abort_if($assignment->user_id !== $user->id, 403);
        $assignment->delete();
        return response()->json(['ok' => true]);
    }
}
