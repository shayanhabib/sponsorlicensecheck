<?php
namespace Tests\Unit;
use App\Services\GovUkSponsorRegisterClient;use GuzzleHttp\Client;use GuzzleHttp\Handler\MockHandler;use GuzzleHttp\HandlerStack;use GuzzleHttp\Psr7\Response;use PHPUnit\Framework\TestCase;
class GovUkSponsorRegisterClientTest extends TestCase{public function test_it_discovers_csv_url():void{$mock=new MockHandler([new Response(200,[], '<a href="/file.csv">CSV</a>')]);$client=new GovUkSponsorRegisterClient(new Client(['handler'=>HandlerStack::create($mock)]));$this->assertSame('https://www.gov.uk/file.csv',$client->latestCsvUrl());}}
