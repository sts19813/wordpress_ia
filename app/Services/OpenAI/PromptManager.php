<?php

namespace App\Services\OpenAI;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

class PromptManager
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ?string $basePath = null,
    ) {}

    public function get(string $name): string
    {
        $path = $this->pathFor($name);

        if (! $this->files->exists($path)) {
            throw new InvalidArgumentException("El prompt [{$name}] no existe.");
        }

        return $this->files->get($path);
    }

    public function exists(string $name): bool
    {
        return $this->files->exists($this->pathFor($name));
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return collect($this->files->files($this->promptPath()))
            ->filter(fn ($file) => $file->getExtension() === 'md')
            ->map(fn ($file) => $file->getBasename('.md'))
            ->sort()
            ->values()
            ->all();
    }

    private function pathFor(string $name): string
    {
        $name = str($name)->replace('\\', '/')->trim('/')->toString();

        if (str_contains($name, '..')) {
            throw new InvalidArgumentException('Nombre de prompt inválido.');
        }

        if (! str_ends_with($name, '.md')) {
            $name .= '.md';
        }

        return $this->promptPath().DIRECTORY_SEPARATOR.$name;
    }

    private function promptPath(): string
    {
        return $this->basePath ?: resource_path('prompts');
    }
}
