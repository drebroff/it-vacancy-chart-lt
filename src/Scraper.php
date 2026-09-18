<?php

declare(strict_types=1);

namespace CvbankasChart;

use Closure;
use RuntimeException;

final class Scraper
{
    public const SOURCE = 'https://en.cvbankas.lt/';

    public function __construct(private readonly HtmlFetcher $fetcher, private readonly ListingParser $parser)
    {
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
}
