<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ENT-K05-03 MoM and ENT-K05-04 Action Item (PRD §7)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meeting_id')->unique()->constrained('meetings')->cascadeOnDelete();
            $table->date('meeting_date');
            $table->text('discussion');
            $table->text('decision');
            $table->text('change_summary')->nullable();
            $table->string('status', 20);                 // MomStatus
            $table->string('written_by', 64);             // F-1 account (TW)
            $table->string('reviewed_by', 64)->nullable(); // F-1 account (TL)
            $table->text('review_note')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('action_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mom_id')->constrained('moms')->cascadeOnDelete();
            $table->text('description');
            $table->string('pic_account_id', 64)->index(); // F-1 account
            $table->date('due_date');
            $table->string('status', 20);                 // ActionItemStatus
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
        Schema::dropIfExists('moms');
    }
};
