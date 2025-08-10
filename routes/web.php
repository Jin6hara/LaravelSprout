<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\ProfilePhotoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
| 機能ごとに整理。機能によってはアクセスが同じでもroleによってRouteを分ける場合がある。
|
*/

//ログイン
Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

//共通
Route::middleware(['auth', 'role:general|admin'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('welcome');
});

//登録
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/register', [AdminController::class, 'showForm'])->name('register.showForm');
    Route::post('/admin/register', [AdminController::class, 'register'])->name('register.submit');
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
});

//プロファイル関連
Route::prefix('profile')->group(function () {
    // general: 自分のプロフィール
    Route::middleware(['auth'])->group(function () {
        Route::get('/', [UsersController::class, 'showProfile'])->name('user.profile');
        Route::patch('/update-field', [UsersController::class, 'updateField'])->name('user.updateField');
        Route::patch('/profile/photo', [ProfilePhotoController::class, 'apply'])->name('profile.photo.apply');
    });

    // admin: 他人のプロフィール
    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('{user}', [UsersController::class, 'showProfile'])->name('admin.user.profile');
        Route::patch('/update-field/{user}', [AdminController::class, 'updateField'])->name('admin.user.updateField');
    });
});
