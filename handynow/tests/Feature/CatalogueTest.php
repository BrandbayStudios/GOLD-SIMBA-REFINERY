<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_trade_categories_are_available_and_pages_render(): void
    {
        $this->seed();
        foreach (['landscaping-gardening', 'domestic-help', 'pest-control-extermination', 'veterinary-services', 'car-detailing-cleaning', 'building-construction'] as $slug) {
            $this->assertTrue(Category::where('slug', $slug)->where('is_active', true)->exists(), $slug);
            $this->get('/services/'.$slug)->assertOk();
        }
        $this->assertTrue(Service::where('slug', 'gas-refill-lpg')->exists());
        $this->assertTrue(Service::where('slug', 'gas-installation-full-system')->exists());
        $this->get('/')->assertOk()->assertSee('Veterinary Services');
        $this->get('/services')->assertOk()->assertSee('Building &amp; Construction', false);
        $this->get('/request?category=veterinary-services')->assertOk();
    }

    public function test_reseeding_does_not_duplicate_or_overwrite_admin_edits(): void
    {
        $this->seed();
        Service::where('slug', 'lawn-mowing-and-lawn-care')->update(['min_price' => 999]);
        $count = Service::count();
        $this->seed();
        $this->assertSame($count, Service::count());
        $this->assertEquals(999, Service::where('slug', 'lawn-mowing-and-lawn-care')->value('min_price'));
    }
}
