<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    /** Each call simulates a fresh HTTP request from a different device. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    public function test_mobile_api_customer_and_provider_flow(): void
    {
        $this->seed(CategorySeeder::class);
        $cat = Category::where('slug', 'plumbing-services')->first();
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.services.0.currency', 'ZMW');

        $token = $this->postJson('/api/v1/auth/register', ['name' => 'Api User', 'email' => 'api@example.com', 'phone' => '0977123123', 'password' => 'secret123'])
            ->assertCreated()->json('token');
        $this->as($token)->getJson('/api/v1/auth/me')->assertJsonPath('user.role', 'customer');

        $pu = User::factory()->provider()->create(['password' => 'secret123']);
        ProviderProfile::create(['user_id' => $pu->id, 'verification_status' => 'approved', 'is_online' => true, 'base_latitude' => -15.41, 'base_longitude' => 28.30])
            ->categories()->sync([$cat->id]);

        $ref = $this->as($token)->postJson('/api/v1/requests', [
            'category_id' => $cat->id, 'description' => 'Burst pipe in the kitchen, water everywhere', 'address' => 'Woodlands, Lusaka',
            'latitude' => -15.42, 'longitude' => 28.31, 'priority' => 'emergency',
        ])->assertCreated()->assertJsonPath('data.status', 'searching')->json('data.reference');

        $ptoken = $this->postJson('/api/v1/auth/login', ['login' => $pu->email, 'password' => 'secret123'])->assertOk()->json('token');
        $this->as($ptoken)->getJson('/api/v1/provider/jobs/available')->assertJsonPath('data.0.reference', $ref);
        $this->as($ptoken)->postJson("/api/v1/provider/jobs/{$ref}/quote", ['amount' => 450, 'eta_minutes' => 15])->assertCreated();
        $this->as($ptoken)->getJson('/api/v1/requests')->assertForbidden();

        $quoteId = $this->as($token)->getJson("/api/v1/requests/{$ref}")->json('data.quotations.0.id');
        $this->as($token)->postJson("/api/v1/requests/{$ref}/quotes/{$quoteId}/accept")->assertOk()->assertJsonPath('data.status', 'provider_assigned');
        $this->as($ptoken)->postJson("/api/v1/provider/jobs/{$ref}/status", ['status' => 'accepted'])->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->as($token)->getJson("/api/v1/requests/{$ref}/live")->assertOk()->assertJsonPath('status', 'accepted');
    }
}
