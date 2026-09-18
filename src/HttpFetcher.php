<?php

declare(strict_types=1);

namespace CvbankasChart;

use Closure;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use RuntimeException;

final class HttpFetcher implements HtmlFetcher
{
    private bool $requested = false;
    private Closure $sleep;

    public function __construct(private readonly ClientInterface $client, ?Closure $sleep = null)
    {
        $this->sleep = $sleep ?? static fn (int $seconds): int => sleep($seconds);
    }

    public function fetch(string $url): string
    {
        $delay = 2;
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            if ($this->requested) {
                ($this->sleep)($delay);
            }
            $this->requested = true;
            try {
                $response = $this->client->request('GET', $url, [
                    'http_errors' => false,
                    'allow_redirects' => false,
                    'connect_timeout' => 15,
                    'timeout' => 45,
                    'headers' => [
                        'User-Agent' => 'CvbankasChart/1.0 (daily public vacancy statistics)',
                        'Accept' => 'text/html',
                        'Accept-Language' => 'en',
                    ],
                ]);
            } catch (TransferException $error) {
                if ($attempt === 3) {
                    throw new RuntimeException('Network failure after 3 attempts: '.$url, 0, $error);
                }
                $delay = 2 * $attempt;
                continue;
            }
            $status = $response->getStatusCode();
            if ($status === 200) {
                if (!str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/html')) {
                    throw new RuntimeException('Expected HTML from '.$url);
                }
                return (string) $response->getBody();
            }
            if (($status === 429 || $status >= 500) && $attempt < 3) {
                $retryAfter = $response->getHeaderLine('Retry-After');
                $delay = max(2 * $attempt, min(30, ctype_digit($retryAfter) ? (int) $retryAfter : 0));
                continue;
            }
            throw new RuntimeException('HTTP '.$status.' while fetching '.$url);
        }
        throw new RuntimeException('Unable to fetch '.$url);
    }
}
