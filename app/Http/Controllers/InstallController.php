<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-time web installer for hosts without SSH (e.g. Hostinger shared hosting).
 * Enabled only while INSTALL_TOKEN is set in .env and storage/app/installed.lock does not exist.
 * Runs migrations, seeds the catalogue + first admin, and links public storage.
 */
class InstallController extends Controller
{
    public function show(Request $request)
    {
        $this->guard($request);
        $db = null;
        try {
            DB::connection()->getPdo();
            $db = 'Connected to database "'.DB::connection()->getDatabaseName().'".';
        } catch (Throwable $e) {
            $db = 'Database connection failed: '.$e->getMessage();
        }

        return response()->view('install', [
            'token' => $request->query('token'),
            'db' => $db,
            'dbOk' => str_starts_with($db, 'Connected'),
            'php' => PHP_VERSION,
            'phpOk' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'writable' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
            'output' => null,
        ]);
    }

    public function run(Request $request)
    {
        $this->guard($request);
        @set_time_limit(300);
        $out = '';
        $ok = true;
        try {
            Artisan::call('migrate', ['--force' => true]);
            $out .= Artisan::output();
            Artisan::call('db:seed', ['--force' => true]);
            $out .= Artisan::output();
            if (! file_exists(public_path('storage'))) {
                try {
                    Artisan::call('storage:link');
                    $out .= Artisan::output();
                } catch (Throwable $e) {
                    $out .= "storage:link failed ({$e->getMessage()}) – uploaded photos will not display until the link is created.\n";
                }
            }
            Artisan::call('optimize:clear');
            file_put_contents(storage_path('app/installed.lock'), now()->toIso8601String());
            $out .= "\nInstallation complete. The installer is now disabled.";
        } catch (Throwable $e) {
            $ok = false;
            $out .= "\nERROR: ".$e->getMessage();
        }

        return response()->view('install', [
            'token' => $request->input('token'), 'db' => null, 'dbOk' => true, 'php' => PHP_VERSION, 'phpOk' => true,
            'writable' => true, 'output' => $out, 'success' => $ok,
        ]);
    }

    private function guard(Request $request): void
    {
        $expected = (string) config('app.install_token');
        $given = (string) ($request->input('token') ?? $request->query('token'));
        abort_if($expected === '' || file_exists(storage_path('app/installed.lock')), 404);
        abort_unless(strlen($expected) >= 16 && hash_equals($expected, $given), 404);
    }
}
