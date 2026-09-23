<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorUploadController;
use App\Http\Controllers\GridPreferenceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectSwitchController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMenuController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', LocaleController::class)->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    // Profile (every role)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

    Route::post('/switch-project', ProjectSwitchController::class)->name('project.switch');
    Route::post('/grid-preferences', GridPreferenceController::class)->name('grid.save');
    Route::post('/editor/upload', EditorUploadController::class)->name('editor.upload');

    // Tickets
    Route::resource('tickets', TicketController::class);
    Route::post('/tickets/{ticket}/status', [TicketController::class, 'status'])->name('tickets.status');
    Route::post('/tickets/{ticket}/comments', [CommentController::class, 'store'])->name('tickets.comments.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Custom ticket folders (cartables)
    Route::post('/ticket-menus', [TicketMenuController::class, 'store'])->name('ticket-menus.store');
    Route::put('/ticket-menus/{ticketMenu}', [TicketMenuController::class, 'update'])->name('ticket-menus.update');
    Route::delete('/ticket-menus/{ticketMenu}', [TicketMenuController::class, 'destroy'])->name('ticket-menus.destroy');

    // Projects are visible to members; managing them needs admin/developer (checked by the policy).
    Route::resource('projects', ProjectController::class);

    Route::middleware('role:admin,developer')->group(function () {
        Route::resource('sprints', SprintController::class)->except('show');
    });

    Route::middleware('role:developer')->group(function () {
        Route::resource('customers', CustomerController::class)->except('show')->parameters(['customers' => 'customer']);
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });
});
