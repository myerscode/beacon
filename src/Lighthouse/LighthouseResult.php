<?php

declare(strict_types=1);

namespace Myerscode\Beacon\Lighthouse;

class LighthouseResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(private readonly array $raw)
    {
    }

    /**
     * Get audit results. No args = all audits. Pass one or more to filter.
     *
     * @return array<string, array<string, mixed>>
     */
    public function audits(Audit|string ...$audits): array
    {
        $allAudits = $this->raw['audits'] ?? [];
        $filter    = array_map(
            fn (Audit|string $a) => $a instanceof Audit ? $a->value : $a,
            $audits,
        );

        if ($filter === []) {
            return $allAudits;
        }

        return array_filter(
            $allAudits,
            fn (string $key): bool => in_array($key, $filter, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Get the full raw Lighthouse JSON as an array.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    /**
     * Save the raw JSON report to disk.
     */
    public function saveJson(string $path): self
    {
        file_put_contents($path, json_encode($this->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this;
    }

    /**
     * Get category scores, optionally filtered.
     *
     * @return array<string, int|null>
     */
    public function scores(Category ...$categories): array
    {
        $allCategories = $this->raw['categories'] ?? [];
        $filter        = array_map(fn (Category $category) => $category->value, $categories);
        $scores        = [];

        foreach ($allCategories as $key => $data) {
            if ($filter !== [] && !in_array($key, $filter, true)) {
                continue;
            }

            $scores[$key] = isset($data['score']) ? (int) round($data['score'] * 100) : null;
        }

        return $scores;
    }
}
