<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DetailPinjam extends Model
{
    protected $table = 'detail_pinjam';
    protected $fillable = ['buku_id','pinjam_id'];
    public $timestamps = false;

    public function pinjam()
    {
        $this->belongsTo('App\Pinjam');
    }

    public function buku()
    {
        return $this->belongsTo('App\Buku');
    }
}
