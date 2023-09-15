<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sku extends Model{
    use HasFactory;
    public $timestamps  = false;
    public function pro(){
        return $this->hasOne('App\Models\Pro', 'id', 'pro_id');
    }

    public function platforms(){
        return $this->hasOne('App\Models\platform', 'id', 'platform');
    }
}
