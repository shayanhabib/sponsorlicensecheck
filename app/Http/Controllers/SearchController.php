<?php
namespace App\Http\Controllers;
use App\Http\Requests\SearchRequest;use App\Models\ImportLog;use App\Models\Sponsor;use App\Repositories\SponsorRepository;
class SearchController extends Controller{public function home(SponsorRepository $repo){return view('home',['stats'=>$repo->statistics(),'latest'=>ImportLog::where('status','success')->latest()->first()]);}public function search(SearchRequest $request,SponsorRepository $repo){return view('search',['results'=>$repo->search($request->validated()),'filters'=>$request->validated()]);}public function company(Sponsor $sponsor){return view('companies.show',['sponsor'=>$sponsor]);}}
