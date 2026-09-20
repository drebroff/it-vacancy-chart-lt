<?php

declare(strict_types=1);

namespace CvbankasChart;

use Symfony\Component\DomCrawler\Crawler;

final class JobDetailParser
{
    public function parse(string $html): string
    {
        $crawler = new Crawler($html);

        // Target the main job ad description container
        $main = $crawler->filter('#jobad_content_main');
        if ($main->count() === 0) {
            $main = $crawler->filter('#jobad_c');
        }
        if ($main->count() === 0) {
            $main = $crawler->filter('article');
        }

        if ($main->count() === 0) {
            return '';
        }

        // Extract text and normalize whitespace
        $text = $main->text();
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
