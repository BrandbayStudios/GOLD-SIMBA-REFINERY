<?php

namespace Tests\Unit;

use App\Services\Geo;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function test_zambian_phone_numbers_are_normalised(): void
    {
        $this->assertSame('260971234567', zm_phone('0971 234 567'));
        $this->assertSame('260971234567', zm_phone('+260 97 123 4567'));
        $this->assertSame('260971234567', zm_phone('971234567'));
    }

    public function test_kwacha_formatting(): void
    {
        $this->assertSame('K1,250.50', zmw(1250.5));
        $this->assertSame('K800', zmw(800, 0));
    }

    public function test_distance_between_lusaka_suburbs(): void
    {
        $km = Geo::distanceKm(-15.4067, 28.3340, -15.4167, 28.2833); // Kabulonga -> CBD
        $this->assertGreaterThan(4, $km);
        $this->assertLessThan(7, $km);
        $this->assertNull(Geo::distanceKm(null, 1, 2, 3));
    }
}
