<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ENT-K05-05 change request and ENT-K05-06 affected E-4 tasks.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id', 64)->index(); // D-1
            $table->foreignUuid('mom_id')->constrained('moms')->cascadeOnDelete();
            $table->text('change_description');
            $table->string('source', 20);
            $table->string('change_type', 20);
            $table->boolean('is_formal_cr');
            $table->string('baseline_item_id', 64)->nullable()->index(); // E-3
            $table->text('impact')->nullable();
            $table->string('analyzed_by', 64)->nullable(); // F-1 account
            $table->string('status', 20);
            $table->text('decision_note')->nullable();
            $table->string('decided_by', 64)->nullable(); // F-1 account
            $table->dateTime('decided_at')->nullable();
            $table->string('created_by', 64); // F-1 account
            $table->timestamps();
        });

        Schema::create('change_request_tasks', function (Blueprint $table) {
            $table->foreignUuid('change_request_id')->constrained('change_requests')->cascadeOnDelete();
            $table->string('task_id', 64); // E-4
            $table->text('impact_note')->nullable();
            $table->string('added_by', 64); // F-1 account
            $table->dateTime('created_at');
            $table->primary(['change_request_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_tasks');
        Schema::dropIfExists('change_requests');
    }
};