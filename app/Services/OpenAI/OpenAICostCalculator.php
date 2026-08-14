<?php

namespace App\Services\OpenAI;

class OpenAICostCalculator
{
    /** Prices in USD per one million tokens. */
    private const TEXT_RATES = [
        'gpt-5.4-mini' => ['input' => 0.75, 'cached_input' => 0.075, 'output' => 4.50],
        'gpt-5-mini' => ['input' => 0.25, 'cached_input' => 0.025, 'output' => 2.00],
        'gpt-5.4-nano' => ['input' => 0.20, 'cached_input' => 0.02, 'output' => 1.25],
        'gpt-4.1-mini' => ['input' => 0.40, 'cached_input' => 0.10, 'output' => 1.60],
    ];

    /** Prices in USD per one million tokens. */
    private const IMAGE_RATES = [
        'gpt-image-2' => ['text_input' => 5.00, 'image_input' => 8.00, 'cached_input' => 2.00, 'image_output' => 30.00],
        'gpt-image-1.5' => ['text_input' => 5.00, 'image_input' => 8.00, 'cached_input' => 2.00, 'image_output' => 32.00],
        'gpt-image-1-mini' => ['text_input' => 2.00, 'image_input' => 2.50, 'cached_input' => 0.25, 'image_output' => 8.00],
        'gpt-image-1' => ['text_input' => 5.00, 'image_input' => 10.00, 'cached_input' => 2.50, 'image_output' => 40.00],
    ];

    /** Output-only reference prices published by OpenAI, in USD per image. */
    private const IMAGE_OUTPUT_ESTIMATES = [
        'gpt-image-2' => [
            'low' => ['1024x1024' => 0.006, '1024x1536' => 0.005, '1536x1024' => 0.005],
            'medium' => ['1024x1024' => 0.053, '1024x1536' => 0.041, '1536x1024' => 0.041],
            'high' => ['1024x1024' => 0.211, '1024x1536' => 0.165, '1536x1024' => 0.165],
        ],
        'gpt-image-1.5' => [
            'low' => ['1024x1024' => 0.009, '1024x1536' => 0.013, '1536x1024' => 0.013],
            'medium' => ['1024x1024' => 0.034, '1024x1536' => 0.050, '1536x1024' => 0.050],
            'high' => ['1024x1024' => 0.133, '1024x1536' => 0.200, '1536x1024' => 0.200],
        ],
        'gpt-image-1-mini' => [
            'low' => ['1024x1024' => 0.005, '1024x1536' => 0.006, '1536x1024' => 0.006],
            'medium' => ['1024x1024' => 0.011, '1024x1536' => 0.015, '1536x1024' => 0.015],
            'high' => ['1024x1024' => 0.036, '1024x1536' => 0.052, '1536x1024' => 0.052],
        ],
        'gpt-image-1' => [
            'low' => ['1024x1024' => 0.011, '1024x1536' => 0.016, '1536x1024' => 0.016],
            'medium' => ['1024x1024' => 0.042, '1024x1536' => 0.063, '1536x1024' => 0.063],
            'high' => ['1024x1024' => 0.167, '1024x1536' => 0.250, '1536x1024' => 0.250],
        ],
    ];

    /** @param array<string, mixed> $usage */
    public function text(string $model, array $usage): float
    {
        $rates = $this->ratesFor($model, self::TEXT_RATES);

        if ($rates === null) {
            return 0.0;
        }

        $input = $this->integer($usage, ['input', 'input_tokens']);
        $cached = min($input, $this->integer($usage, ['cached_input', 'input_details.cached', 'input_tokens_details.cached_tokens']));
        $output = $this->integer($usage, ['output', 'output_tokens']);

        return $this->money(
            (($input - $cached) * $rates['input']) / 1_000_000
            + ($cached * $rates['cached_input']) / 1_000_000
            + ($output * $rates['output']) / 1_000_000
        );
    }

    /** @param array<string, mixed> $usage */
    public function image(string $model, array $usage, ?string $size = null, ?string $quality = null): float
    {
        if ($model === 'original') {
            return 0.0;
        }

        $rates = $this->ratesFor($model, self::IMAGE_RATES);
        $output = $this->integer($usage, ['output', 'output_tokens', 'output_details.image', 'output_tokens_details.image_tokens']);

        if ($rates === null || $output === 0) {
            return $this->estimatedImageOutput($model, $size, $quality);
        }

        $input = $this->integer($usage, ['input', 'input_tokens']);
        $imageInput = min($input, $this->integer($usage, ['input_details.image', 'input_tokens_details.image_tokens']));
        $textInput = $this->integer($usage, ['input_details.text', 'input_tokens_details.text_tokens']);

        if ($textInput === 0 && $imageInput === 0) {
            $textInput = $input;
        }

        $unclassifiedInput = max(0, $input - $textInput - $imageInput);

        return $this->money(
            (($textInput + $unclassifiedInput) * $rates['text_input']) / 1_000_000
            + ($imageInput * $rates['image_input']) / 1_000_000
            + ($output * $rates['image_output']) / 1_000_000
        );
    }

    public function estimatedImageOutput(string $model, ?string $size, ?string $quality): float
    {
        $model = $this->matchingModel($model, self::IMAGE_OUTPUT_ESTIMATES);

        if ($model === null) {
            return 0.0;
        }

        return (float) (self::IMAGE_OUTPUT_ESTIMATES[$model][$quality ?: 'low'][$size ?: '1536x1024'] ?? 0);
    }

    /** @param array<string, array<string, float>> $prices */
    private function ratesFor(string $model, array $prices): ?array
    {
        $key = $this->matchingModel($model, $prices);

        return $key === null ? null : $prices[$key];
    }

    /** @param array<string, mixed> $options */
    private function matchingModel(string $model, array $options): ?string
    {
        if (array_key_exists($model, $options)) {
            return $model;
        }

        foreach (array_keys($options) as $candidate) {
            if (str_starts_with($model, $candidate.'-')) {
                return $candidate;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data @param array<int, string> $paths */
    private function integer(array $data, array $paths): int
    {
        foreach ($paths as $path) {
            $value = data_get($data, $path);

            if (is_numeric($value)) {
                return max(0, (int) $value);
            }
        }

        return 0;
    }

    private function money(float $amount): float
    {
        return round(max(0, $amount), 6);
    }
}
