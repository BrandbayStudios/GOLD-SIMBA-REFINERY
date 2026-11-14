<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Provider;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RequestMessageController;
use App\Http\Controllers\ServiceDirectoryController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

// ---------- Public site ----------
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/services', [ServiceDirectoryController::class, 'index'])->name('services.index');
Route::get('/services/{category}', [ServiceDirectoryController::class, 'category'])->name('services.category');
Route::get('/services/{category}/{service}', [ServiceDirectoryController::class, 'service'])->name('services.show')->scopeBindings();
Route::get('/professionals', [ServiceDirectoryController::class, 'providers'])->name('providers.index');
Route::get('/professionals/{user}', [ServiceDirectoryController::class, 'provider'])->name('providers.show');
Route::get('/api-public/providers-nearby', [ServiceDirectoryController::class, 'nearby'])->name('providers.nearby');
Route::get('/how-it-works', [PageController::class, 'howItWorks'])->name('how-it-works');
Route::get('/faqs', [PageController::class, 'faqs'])->name('faqs');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'sendContact'])->name('contact.send')->middleware('throttle:5,1');

// Emergency / service request (guests and customers)
Route::get('/request', [RequestController::class, 'create'])->name('requests.create');
Route::get('/emergency', [RequestController::class, 'emergency'])->name('requests.emergency');
Route::post('/request', [RequestController::class, 'store'])->name('requests.store')->middleware('throttle:10,1');

// Request tracking (works for guests via secret token link, and for the owning customer)
Route::get('/track', [TrackingController::class, 'lookup'])->name('track.lookup');
Route::post('/track', [TrackingController::class, 'find'])->name('track.find')->middleware('throttle:10,1');
Route::get('/track/{reference}', [TrackingController::class, 'show'])->name('track.show');
Route::get('/track/{reference}/live', [TrackingController::class, 'live'])->name('track.live');
Route::post('/track/{reference}/quotes/{quotation}/accept', [TrackingController::class, 'acceptQuote'])->name('track.quotes.accept');
Route::post('/track/{reference}/quotes/{quotation}/reject', [TrackingController::class, 'rejectQuote'])->name('track.quotes.reject');
Route::post('/track/{reference}/cancel', [TrackingController::class, 'cancel'])->name('track.cancel');
Route::post('/track/{reference}/pay', [TrackingController::class, 'pay'])->name('track.pay');
Route::post('/track/{reference}/review', [TrackingController::class, 'review'])->name('track.review');

// ---------- Auth ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/become-a-provider', [Provider\RegistrationController::class, 'create'])->name('provider.register');
    Route::post('/become-a-provider', [Provider\RegistrationController::class, 'store'])->middleware('throttle:6,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect(auth()->user()->dashboardRoute()))->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/notifications-count', [NotificationController::class, 'count'])->name('notifications.count');
    Route::post('/requests/{serviceRequest}/messages', [RequestMessageController::class, 'store'])->name('messages.store');
    Route::get('/requests/{serviceRequest}/messages', [RequestMessageController::class, 'index'])->name('messages.index');
});

// ---------- Customer dashboard ----------
Route::middleware(['auth', 'role:customer'])->prefix('account')->name('customer.')->group(function () {
    Route::get('/', [Customer\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/requests', [Customer\RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{serviceRequest}', [Customer\RequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{serviceRequest}/cancel', [Customer\RequestController::class, 'cancel'])->name('requests.cancel');
    Route::post('/requests/{serviceRequest}/quotes/{quotation}/accept', [Customer\RequestController::class, 'acceptQuote'])->name('quotes.accept');
    Route::post('/requests/{serviceRequest}/quotes/{quotation}/reject', [Customer\RequestController::class, 'rejectQuote'])->name('quotes.reject');
    Route::post('/requests/{serviceRequest}/pay', [Customer\RequestController::class, 'pay'])->name('requests.pay');
    Route::post('/requests/{serviceRequest}/review', [Customer\RequestController::class, 'review'])->name('requests.review');
    Route::get('/payments', [Customer\DashboardController::class, 'payments'])->name('payments');
    Route::get('/complaints', [Customer\ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/complaints/new', [Customer\ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [Customer\ComplaintController::class, 'store'])->name('complaints.store');
    Route::get('/profile', [Customer\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [Customer\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Customer\ProfileController::class, 'password'])->name('profile.password');
});

// ---------- Provider dashboard ----------
Route::middleware(['auth', 'role:provider'])->prefix('provider')->name('provider.')->group(function () {
    Route::get('/', [Provider\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/availability', [Provider\DashboardController::class, 'availability'])->name('availability');
    Route::post('/location', [Provider\DashboardController::class, 'location'])->name('location');
    Route::get('/jobs', [Provider\JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{serviceRequest}', [Provider\JobController::class, 'show'])->name('jobs.show');
    Route::post('/jobs/{serviceRequest}/quote', [Provider\JobController::class, 'quote'])->name('jobs.quote');
    Route::post('/jobs/{serviceRequest}/decline', [Provider\JobController::class, 'decline'])->name('jobs.decline');
    Route::post('/jobs/{serviceRequest}/status', [Provider\JobController::class, 'status'])->name('jobs.status');
    Route::post('/jobs/{serviceRequest}/release', [Provider\JobController::class, 'release'])->name('jobs.release');
    Route::post('/payments/{payment}/confirm', [Provider\JobController::class, 'confirmPayment'])->name('payments.confirm');
    Route::post('/payments/{payment}/reject', [Provider\JobController::class, 'rejectPayment'])->name('payments.reject');
    Route::get('/earnings', [Provider\DashboardController::class, 'earnings'])->name('earnings');
    Route::get('/reviews', [Provider\DashboardController::class, 'reviews'])->name('reviews');
    Route::get('/profile', [Provider\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [Provider\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/documents', [Provider\ProfileController::class, 'documents'])->name('profile.documents');
    Route::put('/profile/password', [Provider\ProfileController::class, 'password'])->name('profile.password');
    Route::get('/documents/{type}', [Provider\ProfileController::class, 'viewDocument'])->name('documents.view');
});

// ---------- Administration ----------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [Admin\DashboardController::class, 'reports'])->name('reports');
    Route::get('/reports/export', [Admin\DashboardController::class, 'export'])->name('reports.export');

    Route::get('/requests', [Admin\RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{serviceRequest}', [Admin\RequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{serviceRequest}/assign', [Admin\RequestController::class, 'assign'])->name('requests.assign');
    Route::post('/requests/{serviceRequest}/rematch', [Admin\RequestController::class, 'rematch'])->name('requests.rematch');
    Route::post('/requests/{serviceRequest}/cancel', [Admin\RequestController::class, 'cancel'])->name('requests.cancel');
    Route::post('/payments/{payment}/confirm', [Admin\RequestController::class, 'confirmPayment'])->name('payments.confirm');
    Route::post('/payments/{payment}/reject', [Admin\RequestController::class, 'rejectPayment'])->name('payments.reject');
    Route::get('/payments', [Admin\RequestController::class, 'payments'])->name('payments.index');

    Route::get('/providers', [Admin\ProviderController::class, 'index'])->name('providers.index');
    Route::get('/providers/{user}', [Admin\ProviderController::class, 'show'])->name('providers.show');
    Route::post('/providers/{user}/verify', [Admin\ProviderController::class, 'verify'])->name('providers.verify');
    Route::get('/providers/{user}/documents/{type}', [Admin\ProviderController::class, 'document'])->name('providers.document');

    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/status', [Admin\UserController::class, 'toggleStatus'])->name('users.status');

    Route::get('/complaints', [Admin\ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/complaints/{complaint}', [Admin\ComplaintController::class, 'show'])->name('complaints.show');
    Route::put('/complaints/{complaint}', [Admin\ComplaintController::class, 'update'])->name('complaints.update');

    Route::get('/categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [Admin\CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::post('/categories/{category}/services', [Admin\CategoryController::class, 'storeService'])->name('services.store');
    Route::put('/services/{service:id}', [Admin\CategoryController::class, 'updateService'])->name('services.update');

    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/purge-demo', [Admin\SettingsController::class, 'purgeDemo'])->name('settings.purge-demo');
    Route::get('/sms-log', [Admin\SettingsController::class, 'smsLog'])->name('sms-log');
});
