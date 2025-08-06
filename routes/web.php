<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UsersController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/register', [AdminController::class, 'showForm'])->name('register.showForm');
    Route::post('/admin/register', [AdminController::class, 'register'])->name('register.submit');
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
});

Route::middleware(['auth', 'role:general|admin'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('welcome');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
