<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlannerController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\VendorApplicationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Authentication (wired into the on-page auth modal)
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated marketplace actions
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::post('/event-plans', [PlannerController::class, 'store'])->name('planner.store');
    Route::post('/vendor-application', [VendorApplicationController::class, 'store'])->name('vendor.apply');
    Route::post('/vendors/{vendor}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});
