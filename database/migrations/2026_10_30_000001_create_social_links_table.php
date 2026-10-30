<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('social_links', function (Blueprint $table) { $table->string('platform')->primary(); $table->string('url', 2048)->nullable(); }); }
    public function down(): void { Schema::dropIfExists('social_links'); }
};
