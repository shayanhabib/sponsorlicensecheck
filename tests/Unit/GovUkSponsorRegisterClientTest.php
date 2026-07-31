<?php

namespace Tests\Unit;

use App\Services\GovUkSponsorRegisterClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GovUkSponsorRegisterClientTest extends TestCase
{
    public function test_it_discovers_csv_url(): void
    {
        config(['services.govuk.sponsor_register_url' => 'https://www.gov.uk/source']);

        $mock = new MockHandler([
            new Response(200, [], '<a href="/media/live-register.csv">CSV</a>'),
        ]);

        $client = new GovUkSponsorRegisterClient(new Client(['handler' => HandlerStack::create($mock)]));

        $this->assertSame('https://www.gov.uk/media/live-register.csv', $client->latestCsvUrl());
    }
}
