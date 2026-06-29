<?php

namespace App\Services\OpenAI;

class PromptRenderer
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables = []): string
    {
        return preg_replace_callback('/{{\s*([A-Za-z0-9_.-]+)\s*}}/', function (array $matches) use ($variables): string {
            $value = data_get($variables, $matches[1]);

            return $this->stringValue($value);
        }, $template) ?? $template;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                if (collect($value)->every(fn (mixed $item) => is_scalar($item) || $item === null)) {
                    return collect($value)->map(fn (mixed $item) => $this->stringValue($item))->implode(', ');
                }

                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return method_exists($value, '__toString') ? (string) $value : '';
    }
}
