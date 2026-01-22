<?php

declare(strict_types=1);

namespace EnvForm\DTO;

use Illuminate\Support\Collection;

final class NavigationSession
{
    /**
     * @param  Collection<int, EnvVar>  $steps
     * @param  array<string, mixed>  $responses
     * @param  array<int, string>  $history
     */
    public function __construct(
        public Collection $steps,
        public array $responses = [],
        public int $currentIndex = 0,
        public array $history = []
    ) {}

    public function currentStep(): ?EnvVar
    {
        return $this->steps->get($this->currentIndex);
    }

    public function hasNext(): bool
    {
        return $this->currentIndex < $this->steps->count() - 1;
    }

    public function hasPrevious(): bool
    {
        return $this->currentIndex > 0;
    }

    public function next(): void
    {
        if ($this->hasNext()) {
            $this->currentIndex++;
        }
    }

    public function previous(): void
    {
        if ($this->hasPrevious()) {
            $this->currentIndex--;
        }
    }
}
