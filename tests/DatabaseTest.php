<?php

declare(strict_types=1);

namespace CvbankasChart\Tests;

use CvbankasChart\CategoryCatalog;
use CvbankasChart\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    private string $tempDb;
    private Database $database;
    private CategoryCatalog $catalog;

    protected function setUp(): void
    {
        $this->tempDb = sys_get_temp_dir().'/cvbankas_test_'.uniqid('', true).'.sqlite';
        $this->database = new Database($this->tempDb);
        $this->catalog = new CategoryCatalog(__DIR__.'/../config/categories.json');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDb)) {
            unlink($this->tempDb);
        }
    }

    public function testRecordSnapshotAndExport(): void
    {
        $jobs = [
            '101' => ['id' => 101, 'title' => 'Senior Python Developer', 'url' => 'https://en.cvbankas.lt/1-101', 'date' => '1 day ago'],
            '102' => ['id' => 102, 'title' => 'QA Lead', 'url' => 'https://en.cvbankas.lt/1-102', 'date' => '5 hours ago'],
            '103' => ['id' => 103, 'title' => 'Unclassified Specialist', 'url' => 'https://en.cvbankas.lt/1-103', 'date' => '2 days ago'],
        ];

        $jobCategories = [
            '101' => ['python'],
            '102' => ['qa'],
            '103' => [],
        ];

        $counts = ['python' => 1, 'qa' => 1];

        $this->database->recordSnapshot('2026-09-18', $jobs, $jobCategories, $counts, 1);

        $latest = $this->database->getLatestSnapshot();
        $this->assertNotNull($latest);
        $this->assertSame('2026-09-18', $latest['snapshot']['snapshot_date']);
        $this->assertSame(3, (int) $latest['snapshot']['total_vacancies']);
        $this->assertSame(1, $latest['counts']['python']);
        $this->assertSame(1, $latest['counts']['qa']);

        $history = $this->database->exportHistory($this->catalog);
        $this->assertContains('2026-09-18', $history['dates']);
        $this->assertSame([1], $history['series']['python']);
        $this->assertSame([1], $history['series']['qa']);

        $others = $this->database->getUncategorizedVacancies();
        $this->assertCount(1, $others);
        $this->assertSame('Unclassified Specialist', $others[0]['label']);
        $this->assertSame('https://en.cvbankas.lt/1-103', $others[0]['link']);
        $this->assertSame('2 days ago', $others[0]['date']);
    }

    public function testSeedDemoData(): void
    {
        $this->database->seedDemoData($this->catalog, 30);
        $history = $this->database->exportHistory($this->catalog);

        $this->assertCount(30, $history['dates']);
        $this->assertCount(30, $history['series']['python']);
        $this->assertGreaterThan(0, $history['series']['python'][0]);
    }
}
