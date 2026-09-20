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

    public function testCachingAndPurging(): void
    {
        $jobs = [
            '201' => ['id' => 201, 'title' => 'DevOps Engineer', 'url' => 'https://en.cvbankas.lt/1-201', 'description' => 'AWS, Terraform, Kubernetes.'],
            '202' => ['id' => 202, 'title' => 'React Dev', 'url' => 'https://en.cvbankas.lt/1-202', 'description' => 'React, Redux.'],
        ];

        $this->database->recordSnapshot('2026-09-20', $jobs, ['201' => ['devops'], '202' => ['javascript']], ['devops' => 1, 'javascript' => 1], 1);

        // Test cache retrieval
        $cached = $this->database->getCachedDescriptions([201, 202, 999]);
        $this->assertArrayHasKey('201', $cached);
        $this->assertArrayHasKey('202', $cached);
        $this->assertArrayNotHasKey('999', $cached);
        $this->assertSame('DevOps Engineer', $cached['201']['title']);
        $this->assertSame('AWS, Terraform, Kubernetes.', $cached['201']['description']);

        // Purge: currently all are active so 0 purged
        $purged = $this->database->purgeOldVacancies(30);
        $this->assertSame(0, $purged);
    }

    public function testResetData(): void
    {
        $jobs = [
            '301' => ['id' => 301, 'title' => 'C++ Engineer', 'url' => 'https://en.cvbankas.lt/1-301'],
        ];
        $this->database->recordSnapshot('2026-09-19', $jobs, ['301' => ['cpp']], ['cpp' => 1], 1);
        $this->assertNotNull($this->database->getLatestSnapshot());

        $this->database->resetData();

        $this->assertNull($this->database->getLatestSnapshot());
        $history = $this->database->exportHistory($this->catalog);
        $this->assertEmpty($history['dates']);
        $this->assertEmpty($this->database->getUncategorizedVacancies());
    }
}
