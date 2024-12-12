<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('no_hp');
            $table->string('reff');
            $table->string('sn')->default(0);
            $table->string('custno')->default(0);
            $table->string('product_code')->default(0);
            $table->integer('hjual')->default(0);
            $table->integer('adm')->default(0);
            $table->integer('fr_balancejual')->default(0);
            $table->integer('last_balancejual')->default(0);
            $table->integer('hbeli')->default(0);
            $table->integer('fr_balancebeli')->default(0);
            $table->integer('last_balancebeli')->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('callbacks', function (Blueprint $table) {
            $table->id();
            $table->integer('trx_id')->default(0);
            $table->string('api_trxid');
            $table->string('via');
            $table->string('code');
            $table->string('produk');
            $table->string('target');
            $table->string('mtrpln');
            $table->string('note');
            $table->string('token');
            $table->decimal('harga', 10, 2);
            $table->integer('saldo_before_trx');
            $table->integer('saldo_after_trx');
            $table->string('status');
            $table->string('id_user');
            $table->string('nama');
            $table->string('periode');
            $table->integer('jumlah_tagihan');
            $table->decimal('admin', 10, 2);
            $table->decimal('jumlah_bayar', 10, 2);
            $table->timestamps();
        });

        Schema::create('transaction_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reff');
            $table->decimal('debet', 10, 2);
            $table->decimal('kredit', 10, 2);
            $table->decimal('saldo', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('callbacks');
        Schema::dropIfExists('transaction_reports');
    }
};
