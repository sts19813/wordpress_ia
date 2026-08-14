<?php

namespace App\Services;

use App\Models\AiArticle;
use App\Models\AiImage;
use App\Services\OpenAI\OpenAIClient;
use App\Services\OpenAI\OpenAICostCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AiUsageSummaryService
{
    public function __construct(
        private readonly OpenAIClient $client,
        private readonly OpenAICostCalculator $costs,
    ) {}

    /**
     * @return Collection<int, array{
     *   date: Carbon, posts: int, text_tokens: int, image_tokens: int, total_tokens: int,
     *   average_tokens: int, text_cost: float, image_cost: float, total_cost: float,
     *   average_cost: float, projected_100_cost: float
     * }>
     */
    public function daily(Carbon $start, Carbon $end): Collection
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $days = [];

        for ($date = $start->copy(); $date->lt($end); $date->addDay()) {
            $key = $date->toDateString();
            $days[$key] = [
                'date' => $date->copy(),
                'posts' => 0,
                'text_tokens' => 0,
                'image_tokens' => 0,
                'total_tokens' => 0,
                'average_tokens' => 0,
                'text_cost' => 0.0,
                'image_cost' => 0.0,
                'total_cost' => 0.0,
                'average_cost' => 0.0,
                'projected_100_cost' => 0.0,
            ];
        }

        $articles = AiArticle::query()
            ->whereNotNull('generated_at')
            ->where('generated_at', '>=', $start)
            ->where('generated_at', '<', $end)
            ->with(['images' => fn ($query) => $query->where('status', AiImage::STATUS_GENERATED)])
            ->get(['id', 'generated_at', 'model', 'tokens', 'cost']);

        foreach ($articles as $article) {
            $key = $article->generated_at->copy()->timezone($timezone)->toDateString();

            if (! isset($days[$key])) {
                continue;
            }

            $textTokens = $this->totalTokens($article->tokens ?: []);
            $textCost = $article->cost !== null
                ? (float) $article->cost
                : $this->costs->text((string) $article->model, $article->tokens ?: []);
            $imageTokens = 0;
            $imageCost = 0.0;

            foreach ($article->images as $image) {
                $usage = $image->tokens ?: $this->usageFromResponse($image->full_response);
                $imageTokens += $this->totalTokens($usage);
                $imageCost += $image->cost !== null
                    ? (float) $image->cost
                    : $this->costs->image((string) $image->model, $usage, $image->resolution, $image->quality);
            }

            $days[$key]['posts']++;
            $days[$key]['text_tokens'] += $textTokens;
            $days[$key]['image_tokens'] += $imageTokens;
            $days[$key]['total_tokens'] += $textTokens + $imageTokens;
            $days[$key]['text_cost'] += $textCost;
            $days[$key]['image_cost'] += $imageCost;
        }

        return collect($days)->map(function (array $day): array {
            $day['total_cost'] = round($day['text_cost'] + $day['image_cost'], 6);
            $day['text_cost'] = round($day['text_cost'], 6);
            $day['image_cost'] = round($day['image_cost'], 6);
            $day['average_tokens'] = $day['posts'] > 0 ? (int) round($day['total_tokens'] / $day['posts']) : 0;
            $day['average_cost'] = $day['posts'] > 0 ? round($day['total_cost'] / $day['posts'], 6) : 0.0;
            $day['projected_100_cost'] = round($day['average_cost'] * 100, 2);

            return $day;
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $days @return array<string, int|float> */
    public function totals(Collection $days): array
    {
        $posts = (int) $days->sum('posts');
        $tokens = (int) $days->sum('total_tokens');
        $cost = round((float) $days->sum('total_cost'), 6);

        return [
            'posts' => $posts,
            'total_tokens' => $tokens,
            'average_tokens' => $posts > 0 ? (int) round($tokens / $posts) : 0,
            'total_cost' => $cost,
            'average_cost' => $posts > 0 ? round($cost / $posts, 6) : 0.0,
        ];
    }

    /** @param array<string, mixed> $usage */
    private function totalTokens(array $usage): int
    {
        $total = data_get($usage, 'total', data_get($usage, 'total_tokens'));

        if (is_numeric($total)) {
            return max(0, (int) $total);
        }

        return max(0, (int) data_get($usage, 'input', data_get($usage, 'input_tokens', 0)))
            + max(0, (int) data_get($usage, 'output', data_get($usage, 'output_tokens', 0)));
    }

    /** @return array<string, mixed> */
    private function usageFromResponse(?string $response): array
    {
        $decoded = json_decode((string) $response, true);

        return is_array($decoded) ? $this->client->usage($decoded) : [];
    }
}
