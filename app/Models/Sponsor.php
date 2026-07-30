<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;use Illuminate\Support\Str;
class Sponsor extends Model{use HasFactory;protected $fillable=['company_name','slug','town','county','postcode','licence_number','organisation_type','routes','rating','status','imported_at'];protected $casts=['routes'=>'array','imported_at'=>'datetime'];public function getRouteListAttribute():string{return implode(', ',$this->routes??[]);}public static function makeSlug(string $name,?string $postcode=null):string{return Str::slug(trim($name.' '.$postcode));}}
