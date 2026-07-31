<?php

namespace StandaloneSponsorUpdater;

use RuntimeException;
use SplFileObject;

function run(array $argv): int
{
    if (($argv[1] ?? null) !== 'sponsors:update') {
        fwrite(STDERR, "Laravel dependencies are not installed. Run composer install, or run php artisan sponsors:update for the standalone importer.\n");
        return 1;
    }

    $startedAt = microtime(true);
    $sourceUrl = getenv('GOVUK_SPONSOR_REGISTER_URL') ?: 'https://www.gov.uk/government/publications/register-of-licensed-sponsors-workers';

    try {
        echo "Downloading latest GOV.UK sponsor register page...\n";
        $html = http_get($sourceUrl);
        $csvUrl = latest_csv_url($html);
        echo "Latest CSV: {$csvUrl}\n";
        $csv = http_get($csvUrl);

        if (strlen($csv) < 100 || ! str_contains(strtolower(substr($csv, 0, 2048)), 'organisation')) {
            throw new RuntimeException('Downloaded GOV.UK CSV failed validation.');
        }

        $storage = __DIR__ . '/../storage/app';
        if (! is_dir($storage)) {
            mkdir($storage, 0775, true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sponsors_');
        file_put_contents($tmp, mb_convert_encoding($csv, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252'));

        $database = new \SQLite3($storage . '/sponsors.sqlite');
        migrate($database);
        $database->exec('BEGIN IMMEDIATE TRANSACTION');

        $stats = import_csv($database, $tmp);
        $stats['duration'] = microtime(true) - $startedAt;
        $statement = $database->prepare('INSERT INTO import_logs (source_url, csv_url, status, downloaded_rows, imported_rows, updated_rows, skipped_rows, failed_rows, duration_seconds, started_at, finished_at) VALUES (:source_url, :csv_url, :status, :downloaded, :imported, 0, :skipped, :failed, :duration, datetime("now"), datetime("now"))');
        $statement->bindValue(':source_url', $sourceUrl, SQLITE3_TEXT);
        $statement->bindValue(':csv_url', $csvUrl, SQLITE3_TEXT);
        $statement->bindValue(':status', 'success', SQLITE3_TEXT);
        $statement->bindValue(':downloaded', $stats['downloaded'], SQLITE3_INTEGER);
        $statement->bindValue(':imported', $stats['imported'], SQLITE3_INTEGER);
        $statement->bindValue(':skipped', $stats['skipped'], SQLITE3_INTEGER);
        $statement->bindValue(':failed', $stats['failed'], SQLITE3_INTEGER);
        $statement->bindValue(':duration', $stats['duration'], SQLITE3_FLOAT);
        $statement->execute();
        $database->exec('COMMIT');
        @unlink($tmp);

        echo "\nDownloaded rows: {$stats['downloaded']}\n";
        echo "Imported rows: {$stats['imported']}\n";
        echo "Updated rows: 0\n";
        echo "Skipped rows: {$stats['skipped']}\n";
        echo "Failed rows: {$stats['failed']}\n";
        echo 'Import duration: ' . number_format($stats['duration'], 2) . "s\n";
        echo "Standalone SQLite database: storage/app/sponsors.sqlite\n";

        return 0;
    } catch (\Throwable $exception) {
        if (isset($database)) {
            @$database->exec('ROLLBACK');
        }
        fwrite(STDERR, 'Sponsor import failed: ' . $exception->getMessage() . "\n");
        return 1;
    }
}

function http_get(string $url): string
{
    $errors = [];

    foreach ([null, ''] as $proxy) {
        $curl = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_USERAGENT => 'UK Sponsor Licence Checker/1.0',
        ];

        if ($proxy !== null) {
            $options[CURLOPT_PROXY] = $proxy;
        }

        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body !== false && $status < 400) {
            return (string) $body;
        }

        $errors[] = "status={$status}; error={$error}";
    }

    throw new RuntimeException("HTTP request failed for {$url}; " . implode(' | ', $errors));
}

function latest_csv_url(string $html): string
{
    preg_match_all('/href=["\']([^"\']+\.csv(?:\?[^"\']*)?)["\']/i', $html, $matches);

    if (($matches[1] ?? []) === []) {
        throw new RuntimeException('No CSV link found on GOV.UK sponsor register page.');
    }

    $csv = html_entity_decode($matches[1][0]);

    return str_starts_with($csv, 'http') ? $csv : 'https://www.gov.uk' . $csv;
}

function migrate(\SQLite3 $database): void
{
    $database->exec('CREATE TABLE IF NOT EXISTS sponsors (id INTEGER PRIMARY KEY AUTOINCREMENT, source_hash TEXT NOT NULL UNIQUE, company_name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, town TEXT, county TEXT, postcode TEXT, licence_number TEXT, organisation_type TEXT, routes TEXT, rating TEXT, status TEXT, imported_at TEXT, created_at TEXT, updated_at TEXT)');
    $database->exec('CREATE TABLE IF NOT EXISTS import_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, source_url TEXT NOT NULL, csv_url TEXT, status TEXT NOT NULL, downloaded_rows INTEGER DEFAULT 0, imported_rows INTEGER DEFAULT 0, updated_rows INTEGER DEFAULT 0, skipped_rows INTEGER DEFAULT 0, failed_rows INTEGER DEFAULT 0, duration_seconds REAL DEFAULT 0, started_at TEXT, finished_at TEXT)');
}

function import_csv(\SQLite3 $database, string $path): array
{
    $file = new SplFileObject($path);
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
    $headers = [];
    $stats = ['downloaded' => 0, 'imported' => 0, 'skipped' => 0, 'failed' => 0];
    $statement = $database->prepare('INSERT INTO sponsors (source_hash, company_name, slug, town, county, postcode, licence_number, organisation_type, routes, rating, status, imported_at, created_at, updated_at) VALUES (:source_hash, :company_name, :slug, :town, :county, :postcode, :licence_number, :organisation_type, :routes, :rating, :status, datetime("now"), datetime("now"), datetime("now")) ON CONFLICT(source_hash) DO UPDATE SET company_name=excluded.company_name, slug=excluded.slug, town=excluded.town, county=excluded.county, postcode=excluded.postcode, licence_number=excluded.licence_number, organisation_type=excluded.organisation_type, routes=excluded.routes, rating=excluded.rating, status=excluded.status, imported_at=datetime("now"), updated_at=datetime("now")');

    foreach ($file as $row) {
        if (! is_array($row) || $row === [null]) {
            continue;
        }
        if ($headers === []) {
            $headers = normalise_headers($row);
            continue;
        }
        $stats['downloaded']++;
        $data = array_combine($headers, array_pad($row, count($headers), ''));
        if (! is_array($data) || trim((string) ($data['organisation_name'] ?? '')) === '') {
            $stats['skipped']++;
            continue;
        }
        $company = trim((string) $data['organisation_name']);
        $postcode = nullable($data['postcode'] ?? null);
        $licenceNumber = nullable($data['licence_number'] ?? null);
        $routes = routes($data);
        $values = [
            ':source_hash' => sha1(strtolower($company) . '|' . strtolower((string) $postcode) . '|' . strtolower((string) $licenceNumber)),
            ':company_name' => $company,
            ':slug' => slug($company . ' ' . sha1(strtolower($company) . '|' . strtolower((string) $postcode) . '|' . strtolower((string) $licenceNumber))),
            ':town' => nullable($data['town_city'] ?? $data['town'] ?? null),
            ':county' => nullable($data['county'] ?? null),
            ':postcode' => $postcode,
            ':licence_number' => $licenceNumber,
            ':organisation_type' => nullable($data['type_rating'] ?? $data['organisation_type'] ?? null),
            ':routes' => json_encode($routes, JSON_THROW_ON_ERROR),
            ':rating' => nullable($data['rating'] ?? null) ?: rating($data),
            ':status' => nullable($data['status'] ?? null) ?: 'Licensed',
        ];
        foreach ($values as $key => $value) {
            $statement->bindValue($key, $value, $value === null ? SQLITE3_NULL : SQLITE3_TEXT);
        }
        $statement->execute();
        $stats['imported']++;
        if ($stats['imported'] % 5000 === 0) {
            echo '.';
        }
    }

    return $stats;
}

function normalise_headers(array $headers): array
{
    return array_map(function (mixed $header): string {
        $header = strtolower(trim((string) $header));
        $header = str_replace(['&', '/', '-'], [' ', ' ', ' '], $header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';
        return trim($header, '_');
    }, $headers);
}

function routes(array $data): array
{
    $source = (string) ($data['route'] ?? $data['routes'] ?? '');
    return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;|]+/', $source) ?: []))));
}

function rating(array $data): ?string
{
    $text = (string) ($data['type_rating'] ?? '');
    return str_contains($text, 'A rating') ? 'A rating' : (str_contains($text, 'B rating') ? 'B rating' : null);
}

function nullable(mixed $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?: '', '-'));
    return $slug !== '' ? $slug : bin2hex(random_bytes(8));
}
