<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ENT-K05-08 Riwayat Status (PRD §7). Append-only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 30);   // MEETING, MOM, ACTION_ITEM
            $table->uuid('entity_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('changed_by', 64);    // F-1 account
            $table->dateTime('changed_at');
            $table->text('note')->nullable();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
