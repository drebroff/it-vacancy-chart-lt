<?php

declare(strict_types=1);

namespace CvbankasChart;

use InvalidArgumentException;

final class CategoryCatalog
{
    public readonly array $categories;
    public readonly string $version;

    public function __construct(string $path)
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new InvalidArgumentException('Cannot read category configuration.');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $categories = $data['categories'] ?? [];
        if (!is_array($categories) || $categories === []) {
            throw new InvalidArgumentException('At least one category is required.');
        }
        $ids = [];
        foreach ($categories as $category) {
            $id = $category['id'] ?? '';
            if (!preg_match('/^[a-z][a-z0-9-]*$/', $id) || isset($ids[$id])
                || !is_string($category['label'] ?? null) || $category['label'] === ''
                || !in_array($category['group'] ?? '', ['stack', 'role', 'enterprise'], true)) {
                throw new InvalidArgumentException('Invalid or duplicate category: '.$id);
            }
            $ids[$id] = true;
            $aliases = $category['aliases'] ?? [];
            $patterns = $category['patterns'] ?? [];
            if (!is_array($aliases) || !is_array($patterns) || ($aliases === [] && $patterns === [])) {
                throw new InvalidArgumentException('Missing matching rules for '.$id);
            }
            foreach ($aliases as $alias) {
                if (!is_string($alias) || trim($alias) === '') {
                    throw new InvalidArgumentException('Invalid alias for '.$id);
                }
            }
            foreach ($patterns as $pattern) {
                if (!is_string($pattern) || @preg_match('~'.$pattern.'~iu', '') === false) {
                    throw new InvalidArgumentException('Invalid regular expression for '.$id);
                }
            }
        }
        $this->categories = $categories;
        $this->version = hash('sha256', json_encode($categories, JSON_THROW_ON_ERROR));
    }

    public function definitions(): array
    {
        return array_map(static fn (array $c): array => array_intersect_key($c, array_flip(['id', 'label', 'group'])), $this->categories);
    }
}
