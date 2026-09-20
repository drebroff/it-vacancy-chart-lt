<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use CvbankasChart\CategoryCatalog;
use CvbankasChart\Database;
use CvbankasChart\HttpFetcher;
use CvbankasChart\JobDetailParser;
use CvbankasChart\ListingParser;
use CvbankasChart\Scraper;
use CvbankasChart\TitleClassifier;
use GuzzleHttp\Client;

$command = $argv[1] ?? 'help';
$rootDir = dirname(__DIR__);
$configPath = $rootDir.'/config/categories.json';
$dbPath = $rootDir.'/data/cvbankas.sqlite';
$webDataDir = $rootDir.'/web/data';

if (!is_dir($webDataDir) && !mkdir($webDataDir, 0777, true) && !is_dir($webDataDir)) {
    fwrite(STDERR, "Error: cannot create directory {$webDataDir}\n");
    exit(1);
}

$catalog = new CategoryCatalog($configPath);
$database = new Database($dbPath);

switch ($command) {
    case 'collect':
        echo "=== CVbankas IT Vacancy Collector ===\n";
        echo "Time: ".date('Y-m-d H:i:s')."\n";
        echo "Source: ".Scraper::SOURCE."\n";
        echo "Categories: ".count($catalog->categories)."\n\n";

        $client = new Client();
        // Wait 1.5 seconds between requests for rate-limiting listing pages
        $fetcher = new HttpFetcher($client, static fn (int $sec) => usleep($sec * 750000));
        $parser = new ListingParser();
        $detailParser = new JobDetailParser();
        $scraper = new Scraper($fetcher, $parser, $detailParser);
        $classifier = new TitleClassifier($catalog);

        echo "Collecting listing pages from cvbankas.lt...\n";
        $startTime = microtime(true);
        $result = $scraper->collect(static function (int $page, int $maxPage, int $jobsCount): void {
            printf("  -> Page %d of %d parsed (running total: %d jobs)\n", $page, $maxPage, $jobsCount);
        });

        $jobs = $result['jobs'];
        $pages = $result['pages'];
        $duration = round(microtime(true) - $startTime, 1);
        echo "\nListing collection complete in {$duration}s. Total vacancies: ".count($jobs).", Pages: {$pages}\n";

        if (count($jobs) === 0) {
            throw new RuntimeException('No jobs collected; refusing to save empty snapshot.');
        }

        echo "\nFetching vacancy descriptions (with 30-day cache check)...\n";
        $cachedJobs = $database->getCachedDescriptions(array_keys($jobs));
        $detailStart = microtime(true);
        $lastReport = 0;
        $scraper->fetchJobDetails($jobs, $cachedJobs, static function (int $current, int $total, int $cacheHits, int $newFetches) use (&$lastReport): void {
            if ($current === $total || $current - $lastReport >= 25) {
                $lastReport = $current;
                printf("  -> Progress: %d / %d (cache hits: %d, new fetches: %d)\n", $current, $total, $cacheHits, $newFetches);
            }
        });
        $detailDuration = round(microtime(true) - $detailStart, 1);
        echo "Vacancy descriptions enriched in {$detailDuration}s.\n";

        echo "\nClassifying vacancies (title + page description)...\n";
        $jobCategories = [];
        foreach ($jobs as $id => $job) {
            $jobCategories[$id] = $classifier->classify($job['title'], $job['description'] ?? '');
        }
        $categoryCounts = $classifier->count($jobs);

        echo "Top categories in this snapshot:\n";
        arsort($categoryCounts);
        $shown = 0;
        foreach ($categoryCounts as $catId => $cnt) {
            if ($cnt > 0 && ++$shown <= 10) {
                printf("  - %-25s: %d\n", $catId, $cnt);
            }
        }

        $today = date('Y-m-d');
        echo "\nSaving snapshot to SQLite ({$dbPath})...\n";
        $database->recordSnapshot($today, $jobs, $jobCategories, $categoryCounts, $pages);

        $purgedCount = $database->purgeOldVacancies(30);
        if ($purgedCount > 0) {
            echo "Purged {$purgedCount} obsolete vacancies older than 30 days from cache.\n";
        }

        echo "Exporting web artifacts...\n";
        exportWebData($database, $catalog, $webDataDir, $jobs, $categoryCounts, $pages);
        echo "Done! Web data generated at {$webDataDir}/history.json\n";
        break;

    case 'build':
        echo "Building web export from database...\n";
        $latest = $database->getLatestSnapshot();
        $categoryCounts = $latest['counts'] ?? [];
        $pages = (int) ($latest['snapshot']['total_pages'] ?? 1);
        exportWebData($database, $catalog, $webDataDir, [], $categoryCounts, $pages);
        echo "Build successful! Created:\n";
        echo "  - {$webDataDir}/history.json\n";
        echo "  - {$webDataDir}/latest.json\n";
        echo "  - {$webDataDir}/others.json\n";
        break;

    case 'seed-demo':
        $days = isset($argv[2]) && is_numeric($argv[2]) ? (int) $argv[2] : 120;
        echo "Seeding demo historical time-series for {$days} days...\n";
        $database->seedDemoData($catalog, $days);
        $latest = $database->getLatestSnapshot();
        exportWebData($database, $catalog, $webDataDir, [], $latest['counts'] ?? [], 6);
        echo "Demo history seeded and web data updated!\n";
        break;

    case 'status':
        echo "=== Database Status ===\n";
        $latest = $database->getLatestSnapshot();
        if ($latest === null) {
            echo "No snapshots recorded yet. Run `php bin/console.php collect` or `seed-demo`.\n";
            exit(0);
        }
        $snap = $latest['snapshot'];
        echo "Latest snapshot date: {$snap['snapshot_date']}\n";
        echo "Total active vacancies: {$snap['total_vacancies']}\n";
        echo "Total pages: {$snap['total_pages']}\n";
        echo "Created at: {$snap['created_at']}\n";
        echo "\nCategory breakdown:\n";
        foreach ($latest['counts'] as $cat => $cnt) {
            printf("  %-25s: %d\n", $cat, $cnt);
        }
        break;

    case 'reset':
        echo "Resetting all database snapshots and historical data...\n";
        $database->resetData();
        echo "Database wiped clean.\n";
        break;

    default:
        echo "Usage: php bin/console.php [command]\n\n";
        echo "Commands:\n";
        echo "  collect    - Scrape cvbankas.lt, classify vacancies, update DB & export web data\n";
        echo "  build      - Export history.json and latest.json from current DB data\n";
        echo "  seed-demo  - Generate realistic historical curve (e.g. past 120 days) for demo\n";
        echo "  status     - Show latest snapshot and database status\n";
        echo "  reset      - Wipe all historical database snapshots and vacancies\n";
        break;
}

function exportWebData(
    Database $database,
    CategoryCatalog $catalog,
    string $outputDir,
    array $jobs = [],
    array $categoryCounts = [],
    int $pages = 1
): void {
    $history = $database->exportHistory($catalog);
    file_put_contents(
        $outputDir.'/history.json',
        json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    $latest = [
        'date' => $history['last_updated'] ?? date('Y-m-d'),
        'total_vacancies' => end($history['totals']) ?: count($jobs),
        'pages' => $pages,
        'counts' => $categoryCounts,
        'generated_at' => date('c'),
    ];

    file_put_contents(
        $outputDir.'/latest.json',
        json_encode($latest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    $others = $database->getUncategorizedVacancies();
    file_put_contents(
        $outputDir.'/others.json',
        json_encode($others, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}
