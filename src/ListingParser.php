<?php

declare(strict_types=1);

namespace CvbankasChart;

use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

final class ListingParser
{
    public function parse(string $html, int $expectedPage): array
    {
        $crawler = new Crawler($html);
        $root = $crawler->filter('html');
        if ($root->count() !== 1 || $root->attr('lang') !== 'en'
            || $crawler->filter('option[value="76"][selected]')->count() === 0
            || $crawler->filter('#js_id_id_job_ad_list')->count() !== 1) {
            throw new RuntimeException('Unexpected listing structure, language or IT filter on page '.$expectedPage);
        }

        $pagination = $crawler->filter('.pages_ul');
        $maxPage = 1;
        if ($pagination->count() > 0) {
            $current = $pagination->filter('a.current');
            if ($current->count() !== 1 || trim($current->text()) !== (string) $expectedPage) {
                throw new RuntimeException('Wrong current page: expected '.$expectedPage);
            }
            foreach ($pagination->filter('a[href]') as $link) {
                $href = $link->getAttribute('href');
                parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $query);
                $page = $query['page'] ?? '1';
                if (!is_scalar($page) || !ctype_digit((string) $page) || (int) $page < 1 || (int) $page > 1000) {
                    throw new RuntimeException('Invalid pagination number.');
                }
                $maxPage = max($maxPage, (int) $page);
            }
        } elseif ($expectedPage !== 1) {
            throw new RuntimeException('Pagination disappeared on page '.$expectedPage);
        }

        $articles = $crawler->filter('#js_id_id_job_ad_list article.list_article');
        if ($articles->count() === 0) {
            // Until an actual empty-results markup is verified, do not turn a changed page into a false zero.
            throw new RuntimeException('No job articles on page '.$expectedPage.'; refusing an unverified empty snapshot.');
        }
        $jobs = [];
        foreach ($articles as $article) {
            $node = new Crawler($article);
            $link = $node->filter('a.list_a');
            $heading = $link->filter('h3.list_h3');
            if ($link->count() !== 1 || $heading->count() !== 1) {
                throw new RuntimeException('Malformed job article on page '.$expectedPage);
            }
            $url = $link->attr('href');
            $title = trim($heading->text());
            $language = $heading->attr('lang') ?? '';
            if ($title === '' || !str_starts_with($language, 'en')
                || parse_url($url, PHP_URL_HOST) !== 'en.cvbankas.lt'
                || !preg_match('~/1-(\d+)/?$~', parse_url($url, PHP_URL_PATH) ?? '', $match)
                || $node->attr('id') !== 'job_ad_'.$match[1]) {
                throw new RuntimeException('Invalid job identifier or untranslated title on page '.$expectedPage);
            }
            $dateNode = $node->filter('span.txt_list_2');
            $postedDate = $dateNode->count() > 0 ? rtrim(trim($dateNode->text()), '.') : '';
            $jobs[$match[1]] = ['id' => $match[1], 'url' => $url, 'title' => $title, 'date' => $postedDate];
        }
        return ['maxPage' => max($maxPage, $expectedPage), 'jobs' => $jobs];
    }
}
