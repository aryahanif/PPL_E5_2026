<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ENT-K05-01 Rapat (PRD §7)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id', 64)->index();   // D-1
            $table->string('title', 150);
            $table->dateTime('scheduled_at');              // UTC
            $table->string('location', 200)->nullable();
            $table->string('status', 20);                 // MeetingStatus
            $table->string('created_by', 64);             // F-1 account
            $table->timestamps();
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->string('participant_type', 20);       // ParticipantType
            $table->string('account_id', 64)->nullable(); // F-1, when INTERNAL
            $table->string('display_name', 150)->nullable(); // when CLIENT
            $table->boolean('attended')->default(false);
            $table->timestamps();

            $table->unique(['meeting_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
        Schema::dropIfExists('meetings');
    }
};
