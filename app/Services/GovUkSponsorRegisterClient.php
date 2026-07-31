<?php

namespace App\Services;

use GuzzleHttp\Client;
use RuntimeException;

class GovUkSponsorRegisterClient
{
    private readonly Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'timeout' => 120,
            'connect_timeout' => 30,
            'headers' => ['User-Agent' => 'UK Sponsor Licence Checker/1.0'],
        ]);
    }

    public function latestCsvUrl(): string
    {
        $url = config('services.govuk.sponsor_register_url');
        $html = (string) $this->http->get($url)->getBody();

        preg_match_all('/href=["\']([^"\']+\.csv(?:\?[^"\']*)?)["\']/i', $html, $matches);

        if ($matches[1] === []) {
            throw new RuntimeException('No CSV link found on the GOV.UK sponsor register page.');
        }

        $csv = html_entity_decode($matches[1][0]);

        return str_starts_with($csv, 'http') ? $csv : 'https://www.gov.uk' . $csv;
    }

    public function downloadCsv(string $url): string
    {
        $body = (string) $this->http->get($url)->getBody();
        $lower = strtolower(substr($body, 0, 2048));

        if (strlen($body) < 100 || ! str_contains($lower, 'organisation')) {
            throw new RuntimeException('Downloaded GOV.UK CSV failed validation.');
        }

        return mb_convert_encoding($body, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }
}
