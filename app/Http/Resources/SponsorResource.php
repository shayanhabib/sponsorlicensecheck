<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;use Illuminate\Http\Resources\Json\JsonResource;
class SponsorResource extends JsonResource{public function toArray(Request $request):array{return ['id'=>$this->id,'company_name'=>$this->company_name,'town'=>$this->town,'county'=>$this->county,'postcode'=>$this->postcode,'licence_number'=>$this->licence_number,'organisation_type'=>$this->organisation_type,'routes'=>$this->routes,'rating'=>$this->rating,'status'=>$this->status,'imported_at'=>$this->imported_at?->toIso8601String(),'url'=>route('company.show',$this->slug)];}}
