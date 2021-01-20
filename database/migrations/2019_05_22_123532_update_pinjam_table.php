<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePinjamTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pinjam', function (Blueprint $table) {
            $table->dropForeign('fk-borrow-2');
            $table->dropColumn('denda_id');
            $table->unsignedBigInteger('peraturan_id');
            $table->foreign('peraturan_id', 'fk-pinjam')->references('id')->on('peraturan');
            $table->dropColumn('tgl_dikembalikan');
            $table->unsignedBigInteger('petugas_id')->nullable();
            $table->foreign('petugas_id', 'fk-pinjam-2')->references('id')->on('petugas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pinjam', function (Blueprint $table) {
            //
        });
    }
}
