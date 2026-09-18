<?php

declare(strict_types=1);

namespace CvbankasChart\Tests;

use CvbankasChart\ListingParser;
use PHPUnit\Framework\TestCase;

final class ListingParserTest extends TestCase
{
    public function testParsesMockListingPage(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><title>IT Jobs</title></head>
<body>
    <select name="padalinys">
        <option value="76" selected>IT</option>
    </select>
    <div id="js_id_id_job_ad_list">
        <article class="list_article" id="job_ad_1411001">
            <a class="list_a" href="https://en.cvbankas.lt/python-dev/1-1411001">
                <h3 class="list_h3" lang="en">Senior Python Developer</h3>
            </a>
        </article>
        <article class="list_article" id="job_ad_1411002">
            <a class="list_a" href="https://en.cvbankas.lt/qa-lead/1-1411002">
                <h3 class="list_h3" lang="en">QA Automation Lead</h3>
            </a>
        </article>
    </div>
    <ul class="pages_ul">
        <li><a class="current" href="?page=1">1</a></li>
        <li><a href="?page=2">2</a></li>
        <li><a href="?page=3">3</a></li>
    </ul>
</body>
</html>
HTML;

        $parser = new ListingParser();
        $result = $parser->parse($html, 1);

        $this->assertSame(3, $result['maxPage']);
        $this->assertCount(2, $result['jobs']);
        $this->assertSame('Senior Python Developer', $result['jobs']['1411001']['title']);
        $this->assertSame('QA Automation Lead', $result['jobs']['1411002']['title']);
    }
}
