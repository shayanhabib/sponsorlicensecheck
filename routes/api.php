<?php
use App\Http\Controllers\Api\SponsorApiController;use Illuminate\Support\Facades\Route;
Route::get('/search',[SponsorApiController::class,'search']);Route::get('/company/{sponsor}',[SponsorApiController::class,'company']);Route::get('/statistics',[SponsorApiController::class,'statistics']);
