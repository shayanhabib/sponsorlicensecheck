<?php

namespace App\Services;

use App\Data\ImportStats;
use App\Models\ImportLog;
use App\Models\Sponsor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SplFileObject;
use Throwable;

class SponsorImportService
{
    public function __construct(private readonly GovUkSponsorRegisterClient $client)
    {
    }

    public function import(?callable $advance = null): ImportStats
    {
        $stats = new ImportStats();
        $startedAt = microtime(true);
        $log = ImportLog::create([
            'source_url' => config('services.govuk.sponsor_register_url'),
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $csvUrl = $this->client->latestCsvUrl();
            $csv = $this->client->downloadCsv($csvUrl);
            $tmp = tempnam(sys_get_temp_dir(), 'sponsors_');
            file_put_contents($tmp, $csv);

            DB::transaction(function () use ($tmp, $stats, $advance): void {
                $file = new SplFileObject($tmp);
                $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

                $headers = [];
                $batch = [];
                $now = Carbon::now();

                foreach ($file as $row) {
                    if (! is_array($row) || $row === [null]) {
                        continue;
                    }

                    if ($headers === []) {
                        $headers = $this->normaliseHeaders($row);
                        continue;
                    }

                    $stats->downloaded++;
                    $data = array_combine($headers, array_pad($row, count($headers), ''));

                    if (! is_array($data)) {
                        $stats->failed++;
                        continue;
                    }

                    $company = trim((string) ($data['organisation_name'] ?? ''));

                    if ($company === '') {
                        $stats->skipped++;
                        continue;
                    }

                    $postcode = $this->nullable($data['postcode'] ?? null);
                    $licenceNumber = $this->nullable($data['licence_number'] ?? null);
                    $routes = $this->extractRoutes($data);
                    $slug = Sponsor::makeSlug($company, $postcode ?: $licenceNumber ?: null);

                    $batch[] = [
                        'company_name' => $company,
                        'slug' => $slug,
                        'town' => $this->nullable($data['town_city'] ?? $data['town'] ?? null),
                        'county' => $this->nullable($data['county'] ?? null),
                        'postcode' => $postcode,
                        'licence_number' => $licenceNumber,
                        'organisation_type' => $this->nullable($data['type_rating'] ?? $data['organisation_type'] ?? null),
                        'routes' => json_encode($routes, JSON_THROW_ON_ERROR),
                        'rating' => $this->nullable($data['rating'] ?? null) ?? $this->ratingFromType($data),
                        'status' => $this->nullable($data['status'] ?? null) ?? 'Licensed',
                        'imported_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($batch) >= 1000) {
                        $stats->imported += $this->upsert($batch);
                        $batch = [];
                        $advance?->__invoke();
                    }
                }

                if ($batch !== []) {
                    $stats->imported += $this->upsert($batch);
                }
            }, 3);

            @unlink($tmp);
            Cache::forget('statistics');
            $stats->duration = microtime(true) - $startedAt;

            $log->update([
                'csv_url' => $csvUrl,
                'status' => 'success',
                'downloaded_rows' => $stats->downloaded,
                'imported_rows' => $stats->imported,
                'updated_rows' => $stats->updated,
                'skipped_rows' => $stats->skipped,
                'failed_rows' => $stats->failed,
                'duration_seconds' => $stats->duration,
                'finished_at' => now(),
            ]);

            return $stats;
        } catch (Throwable $exception) {
            $stats->duration = microtime(true) - $startedAt;
            $stats->failed++;

            $log->update([
                'status' => 'failed',
                'failed_rows' => $stats->failed,
                'duration_seconds' => $stats->duration,
                'message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            Log::error('Sponsor import failed', ['exception' => $exception]);

            throw $exception;
        }
    }

    private function upsert(array $batch): int
    {
        Sponsor::upsert(
            $batch,
            ['company_name', 'postcode', 'licence_number'],
            ['slug', 'town', 'county', 'organisation_type', 'routes', 'rating', 'status', 'imported_at', 'updated_at']
        );

        return count($batch);
    }

    private function normaliseHeaders(array $headers): array
    {
        return array_map(function (mixed $header): string {
            $header = strtolower(trim((string) $header));
            $header = str_replace(['&', '/', '-'], [' ', ' ', ' '], $header);
            $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';

            return trim($header, '_');
        }, $headers);
    }

    private function extractRoutes(array $data): array
    {
        $source = (string) ($data['route'] ?? $data['routes'] ?? '');
        $routes = preg_split('/[,;|]+/', $source) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $routes))));
    }

    private function ratingFromType(array $data): ?string
    {
        $text = (string) ($data['type_rating'] ?? '');

        return match (true) {
            str_contains($text, 'A rating') => 'A rating',
            str_contains($text, 'B rating') => 'B rating',
            default => null,
        };
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
