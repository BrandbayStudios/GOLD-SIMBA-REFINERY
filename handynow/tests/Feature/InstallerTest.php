<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_installer_is_hidden_without_a_valid_token(): void
    {
        config(['app.install_token' => '']);
        $this->get('/install')->assertNotFound();
        config(['app.install_token' => 'a-long-secret-token-1234']);
        $this->get('/install?token=wrong')->assertNotFound();
        $this->post('/install', ['token' => 'wrong'])->assertNotFound();
    }

    public function test_installer_sets_up_the_platform_once(): void
    {
        config(['app.install_token' => 'a-long-secret-token-1234']);
        $this->get('/install?token=a-long-secret-token-1234')->assertOk()->assertSee('Install HANDY NOW');
        $this->post('/install', ['token' => 'a-long-secret-token-1234'])->assertOk()->assertSee('Installation complete');
        $this->assertTrue(User::where('role', 'admin')->exists());
        $this->assertFileExists(storage_path('app/installed.lock'));
        $this->get('/install?token=a-long-secret-token-1234')->assertNotFound();
    }
}
