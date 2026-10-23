<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ENT-K05-07 Risk & Issue (PRD §7)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id', 64)->index();        // D-1
            $table->string('type', 20);                       // RiskIssueType
            $table->string('title', 150);
            $table->text('description');
            $table->string('impact_level', 20)->nullable();   // ImpactLevel
            $table->text('mitigation')->nullable();
            $table->string('pic_account_id', 64)->index();    // F-1 account
            $table->foreignUuid('source_mom_id')->nullable()->constrained('moms')->nullOnDelete();
            $table->string('status', 20);                     // RiskIssueStatus
            $table->text('resolution_note')->nullable();
            $table->string('reported_by', 64);                // F-1 account (PM)
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_issues');
    }
};
