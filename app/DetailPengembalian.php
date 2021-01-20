<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DetailPengembalian extends Model
{
    protected $table = 'detail_pengembalian';
    
    protected $fillable = ['pengembalian_id', 'buku_id','keterangan'];
}
