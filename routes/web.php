<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\RoleChangeController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\LeaveApplyController;

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
Route::middleware(['auth', 'role:general|admin|super_admin'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('welcome');
});

//カレンダー関連
Route::middleware(['auth'])->group(function () {
    // FullCalendarのJSON
    Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events'); // JSON
    // 自分のカレンダー
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    // 管理者: 任意ユーザーのカレンダー（/calendar/{user}）
    Route::get('/calendar/{user}', [CalendarController::class, 'index'])->middleware('role:admin|super_admin')->name('calendar.index.user');
});

// Forecast関連
Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
    Route::get('/forecast', [CalendarController::class, 'forecast'])
        ->name('calendar.forecast');

    Route::get('/forecast/events', [CalendarController::class, 'forecastEvents'])
        ->name('calendar.forecast.events');
});

// LeaveCalender関連
Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
    Route::get('/leave', [CalendarController::class, 'Leave'])
        ->name('calendar.leaves');

    Route::get('/leave/events', [CalendarController::class, 'LeaveEvents']) //KSON
        ->name('calendar.leave.events');
});

Route::middleware(['auth'])->group(function () {
    // 申請画面（本人用）
    Route::get('/leaveApply', [LeaveApplyController::class, 'create'])->name('leave.apply.create');
    Route::post('/leaveApply', [LeaveApplyController::class, 'store'])->name('leave.apply.store');

    // 管理者: 指定ユーザーで申請を代理作成する画面
    Route::get('/leaveApply/{user}', [LeaveApplyController::class, 'createForUser'])
        ->middleware('role:admin|super_admin')
        ->name('leave.apply.createForUser');
    Route::post('/leaveApply/{user}', [LeaveApplyController::class, 'storeForUser'])
        ->middleware('role:admin|super_admin')
        ->name('leave.apply.storeForUser');

    // 承認画面（承認者向け）
    Route::get('/approvals/{approvalRequest}', [ApprovalController::class, 'show'])
        ->name('approvals.show');
    Route::post('/approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])
        ->name('approvals.approve');
    Route::post('/approvals/{approvalRequest}/deny', [ApprovalController::class, 'deny'])
        ->name('approvals.deny');
});


//登録
Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
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
        Route::patch('/profile/photo/{user}', [ProfilePhotoController::class, 'apply'])->name('profile.photo.apply');
    });

    // admin: 他人のプロフィール
    Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
        Route::get('{user}', [UsersController::class, 'showProfile'])->name('admin.user.profile');
        Route::patch('/update-field/{user}', [UsersController::class, 'updateField'])->name('admin.user.updateField');
        Route::get('/admin/search', [AdminController::class, 'search'])->name('admin.search');
    });
});

// ロール変更申請
Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
    Route::get('{user}/role-change', [RoleChangeController::class, 'showRoleChange'])->name('admin.user.roleChange');
    Route::post('/users/{user}/role-change/apply', [RoleChangeController::class, 'apply'])->name('roleChange.apply');
});

// super_admin: 承認リクエストの表示・承認・却下
Route::middleware(['auth', 'role:super_admin'])->group(function () {

    // ロール承認画面
    Route::get('/approvals/{approvalRequest}', [ApprovalController::class, 'show'])->name('approvals.show'); // 消したらURLは残るが404。name()の定義は使用していない。

    // 承認・却下アクション
    Route::post('/approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approvalRequest}/deny', [ApprovalController::class, 'deny'])->name('approvals.deny');
});

Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    // クリック＝既読→詳細へ
    Route::get('/notifications/{notification}/go', [NotificationController::class, 'go'])->name('notifications.go'); // name('approvals.show')の内容と連携。
});

use App\Http\Controllers\ExpenseEditController;

Route::middleware(['auth'])->group(function () {
    // ユーザー本人: /expenses/edit?year=YYYY&month=M
    Route::get('/expenses/edit', [ExpenseEditController::class, 'selfEdit'])
        ->name('expenses.edit');

    // 管理者: /expenses/{user}/edit?year=YYYY&month=M
    Route::get('/expenses/{user}/edit', [ExpenseEditController::class, 'adminEdit'])
        ->name('expenses.admin.edit');
    // ★ 提出（report単位）
    Route::put('/expenses/reports/{report}/submit', [ExpenseEditController::class, 'submit'])
        ->name('expenses.submit');
});

use App\Http\Controllers\ExpenseApiController;

Route::middleware(['auth'])->group(function () {
    Route::post('/api/expenses',        [ExpenseApiController::class, 'store'])->name('api.expenses.store');
    Route::put('/api/expenses/{expense}', [ExpenseApiController::class, 'update'])->name('api.expenses.update');
    Route::delete('/api/expenses/{expense}', [ExpenseApiController::class, 'destroy'])->name('api.expenses.destroy');
});

use App\Http\Controllers\ExpenseReportController;

Route::middleware(['auth', 'role:admin|super_admin']) // 権限ミドルウェアは環境に合わせて
    ->get('/expenses/report', [ExpenseReportController::class, 'show'])
    ->name('expenses.admin.report');

use App\Http\Controllers\EventAssignController;
use App\Http\Controllers\LeaveController;

Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
    Route::get('/shift_assigner', [EventAssignController::class, 'edit'])->name('calendar.edit');
    Route::post('/shift',        [EventAssignController::class, 'store'])->name('events.store');
    Route::post('/shift/blank', [EventAssignController::class, 'storeBlank'])->name('events.store.blank');
    Route::put('/shift/{event}', [EventAssignController::class, 'update'])->name('events.update');
    // ★ 一括更新（このページに表示されている分だけ送る）
    Route::post('/shifts/bulk-update', [EventAssignController::class, 'bulkUpdate'])->name('events.bulk_update');
    Route::delete('/shift/{event}', [EventAssignController::class, 'destroy'])->name('events.destroy');
    Route::post('/leaves',        [LeaveController::class, 'store'])->name('leaves.store');
});

use App\Http\Controllers\LeaveManageController;

Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
    Route::get('/leave_manager', [LeaveManageController::class, 'edit'])->name('leaves.edit');
    Route::post('/leaves/blank', [LeaveManageController::class, 'storeBlank'])->name('leaves.store.blank');
    Route::put('/leaves/{leave}', [LeaveManageController::class, 'update'])->name('leaves.update');
    // ★ 一括更新（このページに表示されている分だけ送る）
    Route::post('/leaves/bulk-update', [LeaveManageController::class, 'bulkUpdate'])->name('leaves.bulk_update');
    Route::delete('/leaves/{leave}', [LeaveManageController::class, 'destroy'])->name('leaves.destroy');
});

// Absence Report
Route::middleware(['auth'])->group(function () {
    Route::get('/absence_report/{user}', [LeaveController::class, 'absence'])->name('absence.edit');
    Route::get('/all_absence_report/', [LeaveController::class, 'allReport'])->name('absense.all');
    Route::put('/handle_type/{leave}', [LeaveController::class, 'report'])->name('report.update');
});

use App\Http\Controllers\SchoolProfileController;

Route::get('/schools/search', [SchoolProfileController::class, 'search'])
    ->name('schools.search');

use App\Http\Controllers\ScheduleLineController;

Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
    Route::get('/schedule_manager', [ScheduleLineController::class, 'edit'])->name('schedules.edit');
    Route::post('/schedule_lines', [ScheduleLineController::class, 'store'])->name('schedule_lines.store');
    Route::put('/schedule_lines/{line}', [ScheduleLineController::class, 'update'])->name('schedule_lines.update');
    // ★ 一括更新（このページに表示されている分だけ送る）
    Route::post('/schedule_lines/bulk-update', [ScheduleLineController::class, 'bulkUpdate'])->name('schedule_lines.bulk_update');;
    Route::delete('/schedule_lines/{line}', [ScheduleLineController::class, 'destroy'])->name('schedule_lines.destroy');
    Route::post('/schedule_lines/{line}/copy', [ScheduleLineController::class, 'copy'])->name('schedule_lines.copy');
});

use App\Http\Controllers\ScheduleDetailController;

Route::middleware(['auth'])->group(function () {
    Route::get('/schedule_lines/{line}/details', [ScheduleDetailController::class, 'edit'])->name('schedule_details.edit');
    Route::post('/schedule_lines/{line}/details/bulk-update', [ScheduleDetailController::class, 'bulkUpdate'])->name('schedule_details.bulk_update');
    Route::post('/schedule_lines/{line}/details', [ScheduleDetailController::class, 'storeBlank'])->name('schedule_details.store_blank');
    Route::post('/schedule_details/{detail}/copy', [ScheduleDetailController::class, 'copy'])->name('schedule_details.copy');
    Route::delete('/schedule_details/{detail}', [ScheduleDetailController::class, 'destroy'])->name('schedule_details.destroy');

    // レッスンコード検索（メモや種別、分数を取得）
    Route::get('/lessons/by-code/{code}', [ScheduleDetailController::class, 'findLessonByCode'])->name('lessons.by_code');
});

use App\Http\Controllers\ScheduleController;

Route::middleware(['auth'])->group(function () {
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
    Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
});

use App\Http\Controllers\CommuterPassAdvisorController;

Route::middleware(['auth', 'role:admin|super_admin'])->group(function () {
    Route::get('/commuter-pass-advisor', [CommuterPassAdvisorController::class, 'index'])
        ->name('commuter.advisor.index');
});

use App\Http\Controllers\RouteDeclarationController;

Route::middleware(['auth'])->group(function () {
    Route::get('/routes', [RouteDeclarationController::class, 'index'])
        ->name('routes.index');

    // 管理者専用ルート
    Route::get('/routes/{user}', [RouteDeclarationController::class, 'showUser'])
        ->middleware('role:admin|super_admin')
        ->name('routes.user');
});

use App\Http\Controllers\OverTimeController;

// 残業一覧（in_process のみ）
Route::get('/overtime', [OverTimeController::class, 'index'])
    ->name('overtime.index');
