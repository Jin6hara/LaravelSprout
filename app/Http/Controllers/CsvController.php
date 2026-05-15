<?php

namespace App\Http\Controllers;

class CsvController extends Controller
{
    public function index()
    {
        return view('csv.csv_list');
    }
}
