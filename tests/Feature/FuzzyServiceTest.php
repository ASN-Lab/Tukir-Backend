<?php

namespace Tests\Feature;

use App\Services\FuzzyService;
use Tests\TestCase;

class FuzzyServiceTest extends TestCase
{
    private FuzzyService $fuzzy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fuzzy = new FuzzyService();
    }

    public function test_sangat_dekat_ideal_returns_high_risk(): void
    {
        $result = $this->fuzzy->evaluate(2.0, 5.5); // Rear sangat dekat, side ideal
        $this->assertGreaterThanOrEqual(85, $result['fuzzy_risk_score']);
        $this->assertEquals('BAHAYA', $result['fuzzy_status']);
    }

    public function test_jauh_ideal_returns_low_risk(): void
    {
        $result = $this->fuzzy->evaluate(15.0, 5.5); // Rear jauh, side ideal
        $this->assertLessThanOrEqual(10, $result['fuzzy_risk_score']);
        $this->assertEquals('AMAN', $result['fuzzy_status']);
    }

    public function test_sedang_ideal_returns_medium_risk(): void
    {
        $result = $this->fuzzy->evaluate(8.0, 5.5); // Rear sedang, side ideal
        $this->assertGreaterThanOrEqual(30, $result['fuzzy_risk_score']);
        $this->assertLessThanOrEqual(30.1, $result['fuzzy_risk_score']);
        $this->assertEquals('WASPADA', $result['fuzzy_status']);
    }

    public function test_sangat_dekat_miring_kiri_returns_max_risk(): void
    {
        $result = $this->fuzzy->evaluate(1.0, 1.0); // Rear sangat dekat, side miring kiri
        $this->assertGreaterThanOrEqual(95, $result['fuzzy_risk_score']);
        $this->assertEquals('BAHAYA', $result['fuzzy_status']);
    }

    public function test_risk_always_within_range(): void
    {
        foreach ([0, 1, 5, 10, 18.5] as $rear) {
            foreach ([0, 3, 5.5, 8, 11] as $side) {
                $result = $this->fuzzy->evaluate($rear, $side);
                $this->assertGreaterThanOrEqual(0, $result['fuzzy_risk_score']);
                $this->assertLessThanOrEqual(100, $result['fuzzy_risk_score']);
            }
        }
    }
}
