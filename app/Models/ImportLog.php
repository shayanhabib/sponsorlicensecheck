<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportLog extends Model{protected $fillable=['source_url','csv_url','status','downloaded_rows','imported_rows','updated_rows','skipped_rows','failed_rows','duration_seconds','message','started_at','finished_at'];protected $casts=['started_at'=>'datetime','finished_at'=>'datetime'];}
