<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;use App\Models\ImportLog;use App\Models\Sponsor;use App\Services\SponsorImportService;
class DashboardController extends Controller{public function index(){return view('admin.dashboard',['logs'=>ImportLog::latest()->paginate(20),'total'=>Sponsor::count(),'latest'=>ImportLog::latest()->first()]);}public function import(SponsorImportService $service){$service->import();return redirect()->route('admin.dashboard')->with('status','Import completed successfully.');}}
