<?php

declare(strict_types=1);

namespace CvbankasChart;

use PDO;
use RuntimeException;

final class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create database directory: '.$dir);
        }

        $this->pdo = new PDO('sqlite:'.$dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');
        $this->pdo->exec('PRAGMA journal_mode = WAL;');
        $this->initSchema();
    }

    public function initSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS snapshots (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                snapshot_date TEXT UNIQUE NOT NULL,
                total_vacancies INTEGER NOT NULL,
                total_pages INTEGER NOT NULL,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS vacancies (
                id INTEGER PRIMARY KEY,
                title TEXT NOT NULL,
                url TEXT NOT NULL,
                posted_date TEXT,
                first_seen_date TEXT NOT NULL,
                last_seen_date TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE IF NOT EXISTS vacancy_categories (
                vacancy_id INTEGER NOT NULL,
                category_id TEXT NOT NULL,
                PRIMARY KEY (vacancy_id, category_id),
                FOREIGN KEY (vacancy_id) REFERENCES vacancies(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS daily_stats (
                stat_date TEXT NOT NULL,
                category_id TEXT NOT NULL,
                count INTEGER NOT NULL,
                PRIMARY KEY (stat_date, category_id)
            );

            CREATE INDEX IF NOT EXISTS idx_vacancies_active ON vacancies(is_active);
            CREATE INDEX IF NOT EXISTS idx_daily_stats_date ON daily_stats(stat_date);
        ');

        try {
            $this->pdo->exec('ALTER TABLE vacancies ADD COLUMN posted_date TEXT;');
        } catch (\Throwable) {
            // Column already exists
        }
    }

    /**
     * @param string $date e.g. "2026-09-18"
     * @param array<int|string, array{id: int|string, url: string, title: string}> $jobs
     * @param array<int|string, string[]> $jobCategories map of job ID => array of category IDs
     * @param array<string, int> $categoryCounts map of category ID => count
     * @param int $pages total pages crawled
     */
    public function recordSnapshot(
        string $date,
        array $jobs,
        array $jobCategories,
        array $categoryCounts,
        int $pages
    ): void {
        $this->pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare('
                INSERT INTO snapshots (snapshot_date, total_vacancies, total_pages, created_at)
                VALUES (:date, :total, :pages, :created_at)
                ON CONFLICT(snapshot_date) DO UPDATE SET
                    total_vacancies = excluded.total_vacancies,
                    total_pages = excluded.total_pages,
                    created_at = excluded.created_at
            ');
            $stmt->execute([
                ':date' => $date,
                ':total' => count($jobs),
                ':pages' => $pages,
                ':created_at' => $now,
            ]);

            // Mark existing active vacancies as inactive if not in current crawl
            $this->pdo->exec('UPDATE vacancies SET is_active = 0');

            $upsertVacancy = $this->pdo->prepare('
                INSERT INTO vacancies (id, title, url, posted_date, first_seen_date, last_seen_date, is_active)
                VALUES (:id, :title, :url, :posted_date, :first_seen, :last_seen, 1)
                ON CONFLICT(id) DO UPDATE SET
                    title = excluded.title,
                    url = excluded.url,
                    posted_date = COALESCE(excluded.posted_date, vacancies.posted_date),
                    last_seen_date = excluded.last_seen_date,
                    is_active = 1
            ');

            $insertCategory = $this->pdo->prepare('
                INSERT OR IGNORE INTO vacancy_categories (vacancy_id, category_id)
                VALUES (:vacancy_id, :category_id)
            ');

            foreach ($jobs as $job) {
                $jobId = (int) $job['id'];
                $upsertVacancy->execute([
                    ':id' => $jobId,
                    ':title' => $job['title'],
                    ':url' => $job['url'],
                    ':posted_date' => $job['date'] ?? null,
                    ':first_seen' => $date,
                    ':last_seen' => $date,
                ]);

                if (isset($jobCategories[$job['id']])) {
                    foreach ($jobCategories[$job['id']] as $catId) {
                        $insertCategory->execute([
                            ':vacancy_id' => $jobId,
                            ':category_id' => $catId,
                        ]);
                    }
                }
            }

            // Save daily aggregated counts
            $upsertStat = $this->pdo->prepare('
                INSERT INTO daily_stats (stat_date, category_id, count)
                VALUES (:date, :category_id, :count)
                ON CONFLICT(stat_date, category_id) DO UPDATE SET
                    count = excluded.count
            ');

            foreach ($categoryCounts as $catId => $count) {
                $upsertStat->execute([
                    ':date' => $date,
                    ':category_id' => $catId,
                    ':count' => $count,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Exports time-series for web dashboard.
     *
     * @param CategoryCatalog $catalog
     * @return array{
     *     dates: string[],
     *     totals: int[],
     *     categories: array<string, array{id: string, label: string, group: string}>,
     *     series: array<string, int[]>
     * }
     */
    public function exportHistory(CategoryCatalog $catalog): array
    {
        $datesStmt = $this->pdo->query('
            SELECT snapshot_date, total_vacancies
            FROM snapshots
            ORDER BY snapshot_date ASC
        ');
        $snapshots = $datesStmt->fetchAll();

        $dates = [];
        $totals = [];
        foreach ($snapshots as $row) {
            $dates[] = $row['snapshot_date'];
            $totals[] = (int) $row['total_vacancies'];
        }

        $categories = [];
        foreach ($catalog->definitions() as $def) {
            $categories[$def['id']] = $def;
        }

        // Initialize series with zeros for each date
        $series = [];
        foreach (array_keys($categories) as $catId) {
            $series[$catId] = array_fill(0, count($dates), 0);
        }

        $dateIndices = array_flip($dates);

        $statsStmt = $this->pdo->query('
            SELECT stat_date, category_id, count
            FROM daily_stats
            ORDER BY stat_date ASC
        ');

        while ($row = $statsStmt->fetch()) {
            $d = $row['stat_date'];
            $catId = $row['category_id'];
            if (isset($dateIndices[$d]) && isset($series[$catId])) {
                $series[$catId][$dateIndices[$d]] = (int) $row['count'];
            }
        }

        return [
            'dates' => $dates,
            'totals' => $totals,
            'categories' => $categories,
            'series' => $series,
            'last_updated' => end($dates) ?: null,
        ];
    }

    public function getLatestSnapshot(): ?array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM snapshots ORDER BY snapshot_date DESC LIMIT 1
        ');
        $snapshot = $stmt->fetch();
        if (!$snapshot) {
            return null;
        }

        $statsStmt = $this->pdo->prepare('
            SELECT category_id, count FROM daily_stats WHERE stat_date = :date ORDER BY count DESC
        ');
        $statsStmt->execute([':date' => $snapshot['snapshot_date']]);
        $counts = [];
        while ($row = $statsStmt->fetch()) {
            $counts[$row['category_id']] = (int) $row['count'];
        }

        return [
            'snapshot' => $snapshot,
            'counts' => $counts,
        ];
    }

    /**
     * @return array<int, array{date: string, label: string, link: string}>
     */
    public function getUncategorizedVacancies(): array
    {
        $stmt = $this->pdo->query('
            SELECT v.title AS label, v.url AS link, COALESCE(v.posted_date, "") AS date
            FROM vacancies v
            LEFT JOIN vacancy_categories vc ON v.id = vc.vacancy_id
            WHERE v.is_active = 1 AND vc.category_id IS NULL
            ORDER BY v.id DESC
        ');
        return $stmt->fetchAll();
    }

    /**
     * Seeds realistic historical data leading up to today for demo / visual richness.
     */
    public function seedDemoData(CategoryCatalog $catalog, int $days = 120): void
    {
        $current = $this->getLatestSnapshot();
        $baseCounts = $current['counts'] ?? [];

        // Default realistic baseline if no real crawl has happened yet
        $defaults = [
            'javascript' => 55, 'python' => 38, 'java' => 45, 'dotnet' => 36,
            'php' => 18, 'cpp' => 12, 'go' => 14, 'rust' => 5, 'ruby' => 4,
            'android' => 11, 'ios' => 10, 'sap' => 15, 'salesforce' => 12,
            'dynamics' => 9, 'servicenow' => 7, 'pm' => 24, 'qa' => 34,
            'devops' => 30, 'ai' => 16, 'analyst' => 20, 'helpdesk' => 15,
        ];

        foreach ($catalog->categories as $c) {
            if (!isset($baseCounts[$c['id']])) {
                $baseCounts[$c['id']] = $defaults[$c['id']] ?? 10;
            }
        }

        $today = new \DateTimeImmutable();
        $this->pdo->beginTransaction();
        try {
            for ($i = $days; $i >= 1; --$i) {
                $date = $today->sub(new \DateInterval("P{$i}D"))->format('Y-m-d');
                $total = 0;
                $dayCounts = [];

                // Progress factor from 0 to 1 over the timeline
                $progress = 1 - ($i / $days);

                foreach ($baseCounts as $catId => $targetCount) {
                    // Introduce gentle sine wave + slight trend + small random noise
                    $trend = match ($catId) {
                        'ai', 'rust', 'python', 'devops' => ($progress * 8) - 4,
                        'php', 'ruby' => -($progress * 4),
                        default => sin($i / 10) * 3,
                    };

                    $noise = mt_rand(-2, 2);
                    $val = max(1, (int) round($targetCount * 0.85 + $trend + $noise + (sin(($i + crc32($catId) % 20) / 7) * 4)));
                    $dayCounts[$catId] = $val;
                    $total += $val;
                }

                $this->pdo->prepare('
                    INSERT INTO snapshots (snapshot_date, total_vacancies, total_pages, created_at)
                    VALUES (:date, :total, :pages, :created_at)
                    ON CONFLICT(snapshot_date) DO UPDATE SET
                        total_vacancies = excluded.total_vacancies
                ')->execute([
                    ':date' => $date,
                    ':total' => (int) round($total * 0.7), // total distinct vacancies
                    ':pages' => 6,
                    ':created_at' => $date.' 04:00:00',
                ]);

                $upsertStat = $this->pdo->prepare('
                    INSERT INTO daily_stats (stat_date, category_id, count)
                    VALUES (:date, :category_id, :count)
                    ON CONFLICT(stat_date, category_id) DO UPDATE SET
                        count = excluded.count
                ');

                foreach ($dayCounts as $catId => $cnt) {
                    $upsertStat->execute([
                        ':date' => $date,
                        ':category_id' => $catId,
                        ':count' => $cnt,
                    ]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
