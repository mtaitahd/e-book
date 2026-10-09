<?php

namespace Tests\Unit;

use App\Support\RevenueChart;
use PHPUnit\Framework\TestCase;

/**
 * The trend is drawn as inline SVG so the admin gains no chart dependency.
 * These tests pin the geometry and the honest empty behaviour.
 */
class RevenueChartTest extends TestCase
{
    /**
     * @return list<array{key: string, label: string, cents: int, formatted: string}>
     */
    private function points(array $cents): array
    {
        $points = [];
        $day = 1;

        foreach ($cents as $amount) {
            $points[] = [
                'key' => sprintf('2026-05-%02d', $day),
                'label' => sprintf('%d May', $day),
                'cents' => $amount,
                'formatted' => number_format($amount / 100, 2, '.', '').' TZS',
            ];
            $day++;
        }

        return $points;
    }

    public function test_it_renders_an_svg_for_real_data(): void
    {
        $svg = RevenueChart::make($this->points([100000, 250000, 0]), 'TZS', 'day')->render()->toHtml();

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringEndsWith('</svg>', $svg);
        $this->assertStringContainsString('viewBox="0 0 800 260"', $svg);
        $this->assertStringContainsString('width="100%"', $svg);
    }

    public function test_it_is_responsive_and_preserves_aspect_ratio(): void
    {
        $svg = RevenueChart::make($this->points([1000]), 'TZS', 'day')->render()->toHtml();

        $this->assertStringContainsString('width="100%"', $svg);
        $this->assertStringContainsString('height="auto"', $svg);
        $this->assertStringContainsString('preserveAspectRatio="xMidYMid meet"', $svg);
    }

    public function test_it_needs_no_javascript(): void
    {
        $svg = RevenueChart::make($this->points([1000, 2000]), 'TZS', 'day')->render()->toHtml();

        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('onclick', $svg);
        $this->assertStringNotContainsString('onload', $svg);
    }

    public function test_it_is_accessible(): void
    {
        $svg = RevenueChart::make($this->points([1000, 2000]), 'TZS', 'day')->render()->toHtml();

        $this->assertStringContainsString('role="img"', $svg);
        $this->assertStringContainsString('aria-label=', $svg);
        $this->assertStringContainsString('<title>', $svg);
        $this->assertStringContainsString('<desc>', $svg);
    }

    public function test_every_point_is_labelled_with_its_real_value(): void
    {
        $svg = RevenueChart::make($this->points([150000, 0, 50000]), 'TZS', 'day')->render()->toHtml();

        $this->assertStringContainsString('1 May', $svg);
        $this->assertStringContainsString('2 May', $svg);
        $this->assertStringContainsString('3 May', $svg);
        $this->assertStringContainsString('1500.00 TZS', $svg);
        $this->assertStringContainsString('0.00 TZS', $svg);
        $this->assertStringContainsString('500.00 TZS', $svg);
    }

    public function test_a_zero_period_is_reported_as_empty_rather_than_drawn(): void
    {
        $chart = RevenueChart::make($this->points([0, 0, 0]), 'TZS', 'day');

        $this->assertTrue($chart->isEmpty());
        $this->assertSame(0, $chart->peak());
    }

    public function test_a_real_period_is_not_empty(): void
    {
        $chart = RevenueChart::make($this->points([0, 100, 0]), 'TZS', 'day');

        $this->assertFalse($chart->isEmpty());
        $this->assertSame(100, $chart->peak());
    }

    public function test_a_single_point_renders_without_dividing_by_zero(): void
    {
        $svg = RevenueChart::make($this->points([7500]), 'TZS', 'day')->render()->toHtml();

        $this->assertStringContainsString('75.00 TZS', $svg);
        $this->assertStringNotContainsString('NAN', $svg);
        $this->assertStringNotContainsString('INF', $svg);
    }

    public function test_no_point_count_never_breaks_rendering(): void
    {
        $this->assertTrue(RevenueChart::make([], 'TZS', 'day')->isEmpty());
    }

    public function test_the_axis_ceiling_is_a_round_number(): void
    {
        $this->assertSame(100, RevenueChart::niceCeiling(0), 'An empty axis still needs a scale.');
        $this->assertSame(1, RevenueChart::niceCeiling(1));
        $this->assertSame(10, RevenueChart::niceCeiling(9));
        $this->assertSame(100, RevenueChart::niceCeiling(99));
        $this->assertSame(200, RevenueChart::niceCeiling(101));
        $this->assertSame(500, RevenueChart::niceCeiling(480));
        $this->assertSame(1000, RevenueChart::niceCeiling(501));
        $this->assertSame(2000, RevenueChart::niceCeiling(1900));
        $this->assertSame(10000, RevenueChart::niceCeiling(9001));
    }

    public function test_a_long_series_does_not_overflow_the_viewbox(): void
    {
        $cents = [];
        for ($i = 0; $i < 400; $i++) {
            $cents[] = 1000 * ($i + 1);
        }

        $svg = RevenueChart::make($this->points($cents), 'TZS', 'month')->render()->toHtml();

        preg_match_all('/<circle cx="(-?[\d.]+)"/', $svg, $matches);

        foreach ($matches[1] as $x) {
            $this->assertGreaterThanOrEqual(0, (float) $x);
            $this->assertLessThanOrEqual(800, (float) $x);
        }
    }

    public function test_the_total_of_the_series_is_reported(): void
    {
        $chart = RevenueChart::make($this->points([10000, 20000, 30000]), 'TZS', 'day');

        $this->assertSame(60000, $chart->totalCents());
    }

    public function test_the_currency_reaches_the_axis_labels(): void
    {
        $svg = RevenueChart::make($this->points([100000]), 'KES', 'day')->render()->toHtml();

        $this->assertStringContainsString('KES', $svg);
    }
}
