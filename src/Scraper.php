<?php

declare(strict_types=1);

namespace CvbankasChart;

use Closure;
use RuntimeException;

final class Scraper
{
    public const SOURCE = 'https://en.cvbankas.lt/';

    public function __construct(
        private readonly HtmlFetcher $fetcher,
        private readonly ListingParser $parser,
        private readonly ?JobDetailParser $detailParser = null
    ) {
    }

    public static function url(int $page): string
    {
        return self::SOURCE.'?'.http_build_query([
            'padalinys' => [76], 'page' => $page, 'save_locale' => 1, 'translate_ads' => 1,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function collect(?Closure $progress = null): array
    {
        $jobs = [];
        $maxPage = 1;
        $fingerprints = [];
        for ($page = 1; $page <= $maxPage; ++$page) {
            $result = $this->parser->parse($this->fetcher->fetch(self::url($page)), $page);
            $ids = array_keys($result['jobs']);
            sort($ids);
            $fingerprint = hash('sha256', implode(',', $ids));
            if (isset($fingerprints[$fingerprint])) {
                throw new RuntimeException('A complete page of jobs repeated; refusing an incomplete snapshot.');
            }
            $fingerprints[$fingerprint] = true;
            $maxPage = max($maxPage, $result['maxPage']);
            $jobs += $result['jobs'];
            $progress?->__invoke($page, $maxPage, count($jobs));
        }
        return ['jobs' => $jobs, 'pages' => $maxPage];
    }

    /**
     * Enriches jobs with vacancy page descriptions.
     * Reuses cached descriptions when vacancy ID and title match.
     *
     * @param array<int|string, array{id: int|string, url: string, title: string, date?: string, description?: string}> $jobs
     * @param array<string, array{title: string, description: string}> $cachedJobs
     * @param (Closure(int $current, int $total, int $cacheHits, int $newFetches): void)|null $progress
     */
    public function fetchJobDetails(array &$jobs, array $cachedJobs = [], ?Closure $progress = null): void
    {
        $parser = $this->detailParser ?? new JobDetailParser();
        $total = count($jobs);
        $current = 0;
        $cacheHits = 0;
        $newFetches = 0;

        foreach ($jobs as $id => &$job) {
            ++$current;
            $strId = (string) $id;

            // Check cache: ID and title must match and description must be non-empty
            if (isset($cachedJobs[$strId])
                && $cachedJobs[$strId]['title'] === $job['title']
                && trim($cachedJobs[$strId]['description']) !== ''
            ) {
                $job['description'] = $cachedJobs[$strId]['description'];
                ++$cacheHits;
                $progress?->__invoke($current, $total, $cacheHits, $newFetches);
                continue;
            }

            // Fetch detail page
            try {
                $html = $this->fetcher->fetch($job['url']);
                $job['description'] = $parser->parse($html);
            } catch (\Throwable) {
                $job['description'] = '';
            }

            ++$newFetches;
            $progress?->__invoke($current, $total, $cacheHits, $newFetches);

            // Short polite delay between detail requests
            usleep(150000);
        }
        unset($job);
    }
}
