<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;use App\Http\Requests\SearchRequest;use App\Http\Resources\SponsorResource;use App\Models\Sponsor;use App\Repositories\SponsorRepository;
class SponsorApiController extends Controller{public function search(SearchRequest $request,SponsorRepository $repo){return SponsorResource::collection($repo->search($request->validated()));}public function company(Sponsor $sponsor){return new SponsorResource($sponsor);}public function statistics(SponsorRepository $repo){return response()->json($repo->statistics());}}
