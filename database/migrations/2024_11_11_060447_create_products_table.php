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
        Schema::create('product_prabayars', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('name');
            $table->integer('operator_id');
            $table->integer('category_id');
            $table->decimal('price', 10, 2);
            $table->integer('status');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('product_pascabayars', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('name');
            $table->integer('operator_id');
            $table->integer('category_id');
            $table->decimal('biaya_admin', 10, 2)->default(0);
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prabayars');
        Schema::dropIfExists('product_pascabayars');
    }
};
