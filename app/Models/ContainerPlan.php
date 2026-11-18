<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ContainerPlan extends Model {protected $guarded=['id'];protected $casts=['padding'=>'array','crop_bounds'=>'array','equal_padding'=>'boolean','bounds'=>'array','contents'=>'array'];}
