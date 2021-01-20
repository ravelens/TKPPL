<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pengembalian extends Model
{
    protected $table = 'pengembalian';
    protected $fillable = ['pinjam_id', 'petugas_id','tgl_pengembalian'];
    public $dates = ['tgl_pengembalian'];
    public $timestamps = false;
    
}
