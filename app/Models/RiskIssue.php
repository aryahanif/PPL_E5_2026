<?php

namespace App\Models;

use App\Enums\ImpactLevel;
use App\Enums\RiskIssueStatus;
use App\Enums\RiskIssueType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ENT-K05-07 */
class RiskIssue extends Model
{
    use HasUuids;

    public const HISTORY_TYPE = 'RISK_ISSUE';

    protected $fillable = [
        'project_id', 'type', 'title', 'description', 'impact_level', 'mitigation',
        'pic_account_id', 'source_mom_id', 'status', 'resolution_note', 'reported_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => RiskIssueType::class,
            'impact_level' => ImpactLevel::class,
            'status' => RiskIssueStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function sourceMom(): BelongsTo
    {
        return $this->belongsTo(Mom::class, 'source_mom_id');
    }
}
