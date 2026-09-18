<?php

declare(strict_types=1);

namespace CvbankasChart\Tests;

use CvbankasChart\CategoryCatalog;
use PHPUnit\Framework\TestCase;

final class CategoryCatalogTest extends TestCase
{
    public function testLoadsValidConfiguration(): void
    {
        $catalog = new CategoryCatalog(__DIR__.'/../config/categories.json');
        $this->assertGreaterThan(15, count($catalog->categories));

        $ids = array_column($catalog->categories, 'id');
        $this->assertContains('javascript', $ids);
        $this->assertContains('python', $ids);
        $this->assertContains('java', $ids);
        $this->assertContains('dotnet', $ids);
        $this->assertContains('android', $ids);
        $this->assertContains('ios', $ids);
        $this->assertContains('sap', $ids);
        $this->assertContains('salesforce', $ids);
        $this->assertContains('dynamics', $ids);
        $this->assertContains('servicenow', $ids);
        $this->assertContains('pm', $ids);
        $this->assertContains('qa', $ids);
        $this->assertContains('devops', $ids);
        $this->assertContains('ai', $ids);
    }

    public function testCategoryDefinitionsIncludeGroup(): void
    {
        $catalog = new CategoryCatalog(__DIR__.'/../config/categories.json');
        $defs = $catalog->definitions();

        $groups = array_unique(array_column($defs, 'group'));
        $this->assertContains('stack', $groups);
        $this->assertContains('enterprise', $groups);
        $this->assertContains('role', $groups);
    }
}
