<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class SearchRequest extends FormRequest{public function authorize():bool{return true;}public function rules():array{return ['q'=>['nullable','string','max:120'],'town'=>['nullable','string','max:80'],'county'=>['nullable','string','max:80'],'route'=>['nullable','string','max:120'],'rating'=>['nullable','string','max:64'],'status'=>['nullable','string','max:64'],'sort'=>['nullable','in:alphabetical,newest']];}}
