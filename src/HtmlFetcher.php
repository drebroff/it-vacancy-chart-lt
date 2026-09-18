<?php

declare(strict_types=1);

namespace CvbankasChart;

interface HtmlFetcher
{
    public function fetch(string $url): string;
}
