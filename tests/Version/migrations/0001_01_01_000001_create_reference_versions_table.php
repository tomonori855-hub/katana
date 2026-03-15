<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->dateTime('activated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_versions');
    }
};
