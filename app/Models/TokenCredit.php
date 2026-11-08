<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TokenCredit extends Model {
 protected $fillable=['amount','source','description','grant_key'];
 protected $casts=['amount'=>'integer'];
 public function user(){return $this->belongsTo(User::class);}
}
