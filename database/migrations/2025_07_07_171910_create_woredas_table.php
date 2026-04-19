<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { if (Schema::hasTable('woredas')) return; Schema::create('woredas', function (Blueprint $table) { $table->id(); $table->foreignId('region_id')->constrained('regions'); $table->string('name'); $table->text('description')->nullable(); $table->timestamps(); }); } public function down(): void { Schema::dropIfExists('woredas'); } };
