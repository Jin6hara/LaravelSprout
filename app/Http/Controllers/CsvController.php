<?php

namespace App\Http\Controllers;

use App\Models\User;

class CsvController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);
        return view('csv.csv_list');
    }
}
