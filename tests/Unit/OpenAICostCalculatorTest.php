<?php

namespace Tests\Unit;

use App\Services\OpenAI\OpenAICostCalculator;
use PHPUnit\Framework\TestCase;

class OpenAICostCalculatorTest extends TestCase
{
    public function test_it_calculates_text_cost_for_snapshot_models(): void
    {
        $calculator = new OpenAICostCalculator;

        $cost = $calculator->text('gpt-5.4-mini-2026-03-17', [
            'input' => 1500,
            'cached_input' => 500,
            'output' => 1000,
            'total' => 2500,
        ]);

        $this->assertSame(0.005288, $cost);
    }

    public function test_it_calculates_image_cost_from_reported_usage(): void
    {
        $calculator = new OpenAICostCalculator;

        $cost = $calculator->image('gpt-image-2', [
            'input' => 306,
            'output' => 5488,
            'total' => 5794,
            'input_details' => ['text' => 306],
            'output_details' => ['image' => 5488],
        ], '1536x1024', 'high');

        $this->assertSame(0.16617, $cost);
    }

    public function test_it_estimates_image_output_when_historical_usage_is_missing(): void
    {
        $calculator = new OpenAICostCalculator;

        $this->assertSame(0.005, $calculator->estimatedImageOutput('gpt-image-2', '1536x1024', 'low'));
        $this->assertSame(0.2, $calculator->estimatedImageOutput('gpt-image-1.5', '1536x1024', 'high'));
    }
}
