<?php

declare(strict_types=1);

namespace CvbankasChart;

final class TitleClassifier
{
    private array $rules = [];

    public function __construct(CategoryCatalog $catalog)
    {
        foreach ($catalog->categories as $category) {
            $patterns = [];
            foreach ($category['aliases'] ?? [] as $alias) {
                $literal = preg_quote(self::normalize($alias), '~');
                $patterns[] = '(?<![\p{L}\p{N}_])'.$literal.'(?![\p{L}\p{N}_+#])';
            }
            $this->rules[$category['id']] = array_merge($patterns, $category['patterns'] ?? []);
        }
    }

    public function classify(string $title): array
    {
        $title = self::normalize($title);
        $matched = [];
        foreach ($this->rules as $id => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('~'.$pattern.'~iu', $title) === 1) {
                    $matched[] = $id;
                    break;
                }
            }
        }
        return $matched;
    }

    public function count(array $jobs): array
    {
        $counts = array_fill_keys(array_keys($this->rules), 0);
        foreach ($jobs as $job) {
            foreach ($this->classify($job['title']) as $id) {
                ++$counts[$id];
            }
        }
        return $counts;
    }

    private static function normalize(string $value): string
    {
        $value = str_replace(['–', '—', '‑', '−'], '-', $value);
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
