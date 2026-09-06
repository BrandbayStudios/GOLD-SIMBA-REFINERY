<?php

use App\Http\Controllers\Api\Admin\AdvertisementController as AdminAdvertisementController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ClaimController as AdminClaimController;
use App\Http\Controllers\Api\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Api\Admin\LocationController as AdminLocationController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\Admin\VendorController as AdminVendorController;
use App\Http\Controllers\Api\Admin\FeaturedController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FavouriteController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\Vendor\BookingController as VendorBookingController;
use App\Http\Controllers\Api\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\Api\Vendor\EnquiryController as VendorEnquiryController;
use App\Http\Controllers\Api\Vendor\PackageController as VendorPackageController;
use App\Http\Controllers\Api\Vendor\PortfolioController as VendorPortfolioController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['ok' => true, 'service' => 'EventHub API', 'time' => now()]));

Route::prefix('v1')->group(function () {

    // ---------- Auth ----------
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/register-vendor', [AuthController::class, 'registerVendor']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
    });

    // ---------- Public catalogue ----------
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/vendors', [VendorController::class, 'index']);
    Route::get('/vendors/{vendor}', [VendorController::class, 'show']);
    Route::get('/vendors/{vendor}/reviews', [ReviewController::class, 'index']);
    Route::post('/vendors/{vendor}/enquiries', [EnquiryController::class, 'store']);

    // ---------- Authenticated customer actions ----------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/vendors/{vendor}/reviews', [ReviewController::class, 'store']);
        Route::post('/vendors/{vendor}/favourite', [FavouriteController::class, 'toggle']);
        Route::get('/favourites', [FavouriteController::class, 'index']);
        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::post('/events/{event}/vendors/{vendor}', [EventController::class, 'addVendor']);
    });

    // ---------- Vendor dashboard ----------
    Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->group(function () {
        Route::get('/stats', [VendorDashboardController::class, 'stats']);
        Route::get('/profile', [VendorDashboardController::class, 'profile']);
        Route::put('/profile', [VendorDashboardController::class, 'updateProfile']);

        Route::get('/portfolio', [VendorPortfolioController::class, 'index']);
        Route::post('/portfolio', [VendorPortfolioController::class, 'store']);
        Route::delete('/portfolio/{media}', [VendorPortfolioController::class, 'destroy']);

        Route::get('/packages', [VendorPackageController::class, 'index']);
        Route::post('/packages', [VendorPackageController::class, 'store']);
        Route::put('/packages/{package}', [VendorPackageController::class, 'update']);
        Route::delete('/packages/{package}', [VendorPackageController::class, 'destroy']);

        Route::get('/enquiries', [VendorEnquiryController::class, 'index']);
        Route::patch('/enquiries/{enquiry}', [VendorEnquiryController::class, 'updateStatus']);

        Route::get('/bookings', [VendorBookingController::class, 'index']);
        Route::patch('/bookings/{booking}', [VendorBookingController::class, 'updateStatus']);
    });

    // ---------- Admin panel ----------
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('/stats', [AdminStatsController::class, 'index']);

        Route::get('/vendors', [AdminVendorController::class, 'index']);
        Route::get('/vendors/{vendor}', [AdminVendorController::class, 'show']);
        Route::patch('/vendors/{vendor}/status', [AdminVendorController::class, 'updateStatus']);
        Route::delete('/vendors/{vendor}', [AdminVendorController::class, 'destroy']);

        Route::get('/customers', [AdminCustomerController::class, 'index']);
        Route::delete('/customers/{customer}', [AdminCustomerController::class, 'destroy']);

        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);

        Route::get('/locations', [AdminLocationController::class, 'index']);
        Route::post('/locations', [AdminLocationController::class, 'store']);
        Route::delete('/locations/{location}', [AdminLocationController::class, 'destroy']);

        Route::get('/claims', [AdminClaimController::class, 'index']);
        Route::patch('/claims/{claim}', [AdminClaimController::class, 'updateStatus']);

        Route::get('/reviews', [AdminReviewController::class, 'index']);
        Route::patch('/reviews/{review}', [AdminReviewController::class, 'updateStatus']);
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy']);

        Route::get('/enquiries', [AdminEnquiryController::class, 'index']);
        Route::get('/bookings', [AdminBookingController::class, 'index']);

        Route::get('/featured', [FeaturedController::class, 'index']);
        Route::post('/featured/{vendor}', [FeaturedController::class, 'store']);
        Route::delete('/featured/{vendor}', [FeaturedController::class, 'destroy']);

        Route::get('/advertisements', [AdminAdvertisementController::class, 'index']);
        Route::post('/advertisements', [AdminAdvertisementController::class, 'store']);
        Route::put('/advertisements/{advertisement}', [AdminAdvertisementController::class, 'update']);
        Route::delete('/advertisements/{advertisement}', [AdminAdvertisementController::class, 'destroy']);

        Route::get('/reports', [AdminReportController::class, 'index']);
    });
});
