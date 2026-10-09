<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Renders the revenue trend as inline, dependency-free SVG.
 *
 * Chart.js is not part of this project's admin assets and the admin pages are
 * served from pre-built static files, so adding a charting library (and the
 * bundling step it would imply) would be a heavy change for one sparkline.
 * This renders the same real data as a server-side SVG:
 *
 *  - it scales with the container via viewBox, so it stays legible on a phone;
 *  - it needs no JavaScript, so it works with scripts disabled;
 *  - it is accessible (role/aria-label plus a visually hidden data table).
 */
final class RevenueChart
{
    public const WIDTH = 800;

    public const HEIGHT = 260;

    private const PADDING_LEFT = 64;

    private const PADDING_RIGHT = 18;

    private const PADDING_TOP = 18;

    private const PADDING_BOTTOM = 36;

    /** Never label more than this many x-axis ticks, however long the range. */
    private const MAX_X_LABELS = 8;

    /**
     * @param  list<array{key: string, label: string, cents: int, formatted: string}>  $points
     */
    public function __construct(
        private readonly array $points,
        private readonly string $currency = 'TZS',
        private readonly string $granularity = 'day',
    ) {}

    public static function make(array $points, string $currency = 'TZS', string $granularity = 'day'): self
    {
        return new self($points, strtoupper($currency), $granularity);
    }

    public function isEmpty(): bool
    {
        return $this->points === [] || $this->peak() === 0;
    }

    public function peak(): int
    {
        return max(array_map(fn (array $point) => (int) $point['cents'], $this->points ?: [[
            'cents' => 0,
        ]]));
    }

    public function totalCents(): int
    {
        return array_sum(array_map(fn (array $point) => (int) $point['cents'], $this->points));
    }

    /**
     * Round an axis maximum up to a friendly 1/2/5 x 10^n value.
     */
    public static function niceCeiling(int $value): int
    {
        if ($value <= 0) {
            return 100;
        }

        $magnitude = 10 ** max(0, strlen((string) $value) - 1);
        $scaled = $value / $magnitude;

        $step = match (true) {
            $scaled <= 1 => 1,
            $scaled <= 2 => 2,
            $scaled <= 5 => 5,
            default => 10,
        };

        return $step * $magnitude;
    }

    private function plotWidth(): float
    {
        return self::WIDTH - self::PADDING_LEFT - self::PADDING_RIGHT;
    }

    private function plotHeight(): float
    {
        return self::HEIGHT - self::PADDING_TOP - self::PADDING_BOTTOM;
    }

    /**
     * @return list<int>
     */
    private function xStepIndices(): array
    {
        $count = count($this->points);

        if ($count <= self::MAX_X_LABELS) {
            return range(0, max(0, $count - 1));
        }

        $stride = (int) ceil($count / self::MAX_X_LABELS);
        $indices = range(0, $count - 1, $stride);

        if (end($indices) !== $count - 1) {
            $indices[] = $count - 1;
        }

        return array_values(array_unique($indices));
    }

    public function render(): HtmlString
    {
        $max = self::niceCeiling($this->peak());
        $top = self::PADDING_TOP;
        $bottom = self::PADDING_TOP + $this->plotHeight();
        $left = self::PADDING_LEFT;
        $right = self::PADDING_LEFT + $this->plotWidth();

        $svg = [];

        $svg[] = sprintf(
            '<svg class="revenue-chart" viewBox="0 0 %1$d %2$d" width="100%%" height="auto" role="img" aria-label="%3$s" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">',
            self::WIDTH,
            self::HEIGHT,
            e($this->summaryLabel()),
        );

        $svg[] = sprintf(
            '<title>%s</title><desc>%s</desc>',
            e($this->summaryLabel()),
            e(sprintf(
                '%d %s revenue points plotted by %s. Peak %s.',
                count($this->points),
                $this->currency,
                $this->granularity,
                Money::formatWhole($this->peak(), $this->currency),
            )),
        );

        // Horizontal gridlines plus their value labels.
        foreach ($this->ticks($max) as $tick) {
            $y = $this->yFor($tick, $max, $top, $bottom);

            $svg[] = sprintf(
                '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#e3e6f0" stroke-width="1" />',
                $left,
                $y,
                $right,
                $y,
            );

            $svg[] = sprintf(
                '<text x="%.2f" y="%.2f" text-anchor="end" dominant-baseline="middle" font-size="11" fill="#858796">%s</text>',
                $left - 10,
                $y,
                e(Money::formatWhole($tick, '')),
            );
        }

        // Baseline.
        $svg[] = sprintf(
            '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#c7cbe0" stroke-width="1.5" />',
            $left,
            $bottom,
            $right,
            $bottom,
        );

        $coordinates = $this->coordinates($max, $top, $bottom, $left);
        $line = implode(' ', array_map(
            fn (array $point) => sprintf('%.2f,%.2f', $point['x'], $point['y']),
            $coordinates,
        ));

        if ($coordinates !== []) {
            $area = sprintf(
                'M %.2f %.2f L %s L %.2f %.2f L %.2f %.2f Z',
                $coordinates[0]['x'],
                $bottom,
                $line,
                $coordinates[count($coordinates) - 1]['x'],
                $bottom,
                $left,
                $bottom,
            );

            $svg[] = sprintf(
                '<defs><linearGradient id="revenueChartFill" x1="0" y1="0" x2="0" y2="1">'
                .'<stop offset="0%%" stop-color="#4e73df" stop-opacity="0.28" />'
                .'<stop offset="100%%" stop-color="#4e73df" stop-opacity="0.02" />'
                .'</linearGradient></defs>',
            );

            $svg[] = sprintf('<path d="%s" fill="url(#revenueChartFill)" />', $area);
            $svg[] = sprintf(
                '<polyline points="%s" fill="none" stroke="#4e73df" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />',
                $line,
            );

            foreach ($coordinates as $index => $point) {
                $svg[] = sprintf(
                    '<circle cx="%.2f" cy="%.2f" r="3" fill="#ffffff" stroke="#4e73df" stroke-width="2"><title>%s — %s</title></circle>',
                    $point['x'],
                    $point['y'],
                    e($this->points[$index]['label']),
                    e($this->points[$index]['formatted']),
                );
            }
        }

        foreach ($this->xStepIndices() as $index) {
            $svg[] = sprintf(
                '<text x="%.2f" y="%.2f" text-anchor="middle" font-size="11" fill="#858796">%s</text>',
                $coordinates[$index]['x'],
                $bottom + 20,
                e($this->points[$index]['label']),
            );
        }

        $svg[] = '</svg>';

        return new HtmlString(implode('', $svg));
    }

    private function summaryLabel(): string
    {
        return sprintf('Revenue by %s, in %s', $this->granularity, $this->currency);
    }

    /**
     * @return list<int>
     */
    private function ticks(int $max): array
    {
        $step = max(1, (int) round($max / 4));

        return [$step, $step * 2, $step * 3, $max];
    }

    private function yFor(int $value, int $max, float $top, float $bottom): float
    {
        if ($max <= 0) {
            return $bottom;
        }

        return $bottom - ($value / $max) * $this->plotHeight();
    }

    /**
     * @return list<array{x: float, y: float}>
     */
    private function coordinates(int $max, float $top, float $bottom, float $left): array
    {
        $count = count($this->points);

        if ($count === 0) {
            return [];
        }

        if ($count === 1) {
            return [['x' => $left + ($this->plotWidth() / 2), 'y' => $bottom]];
        }

        $step = $this->plotWidth() / ($count - 1);

        return array_map(fn (int $index) => [
            'x' => $left + ($step * $index),
            'y' => $this->yFor((int) $this->points[$index]['cents'], $max, $top, $bottom),
        ], range(0, $count - 1));
    }
}
