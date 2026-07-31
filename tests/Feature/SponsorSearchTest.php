<?php
namespace Tests\Feature;
use App\Models\Sponsor;use Illuminate\Foundation\Testing\RefreshDatabase;use Tests\TestCase;
class SponsorSearchTest extends TestCase{use RefreshDatabase;public function test_home_page_loads():void{$this->get('/')->assertOk()->assertSee('UK Sponsor Licence Checker');}public function test_api_search_returns_sponsors():void{Sponsor::create(['source_hash'=>sha1('real-sponsor-ltd|ab1|'),'company_name'=>'Real Sponsor Ltd','slug'=>'real-sponsor-ltd-ab1','postcode'=>'AB1','routes'=>['Skilled Worker'],'status'=>'Licensed']);$this->getJson('/api/search?q=Real')->assertOk()->assertJsonFragment(['company_name'=>'Real Sponsor Ltd']);}}
