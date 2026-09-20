<?php

declare(strict_types=1);

namespace CvbankasChart\Tests;

use CvbankasChart\JobDetailParser;
use PHPUnit\Framework\TestCase;

final class JobDetailParserTest extends TestCase
{
    public function testParsesJobAdContentMain(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div id="header">Some Navigation</div>
    <div id="jobad_content_main">
        <h1>Senior Developer</h1>
        <div class="jobad_txt">
            <p>We are looking for an experienced Python developer with Django knowledge.</p>
        </div>
    </div>
    <div id="footer">Footer Info</div>
</body>
</html>
HTML;

        $parser = new JobDetailParser();
        $text = $parser->parse($html);

        $this->assertStringContainsString('Senior Developer', $text);
        $this->assertStringContainsString('Python developer with Django knowledge', $text);
        $this->assertStringNotContainsString('Some Navigation', $text);
        $this->assertStringNotContainsString('Footer Info', $text);
    }

    public function testFallbackToArticleIfMainMissing(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <article id="jobad_c">
        <h2>QA Engineer</h2>
        <p>Testing with Cypress and Selenium.</p>
    </article>
</body>
</html>
HTML;

        $parser = new JobDetailParser();
        $text = $parser->parse($html);

        $this->assertStringContainsString('QA Engineer', $text);
        $this->assertStringContainsString('Testing with Cypress', $text);
    }
}
