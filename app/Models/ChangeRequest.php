<?php

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeSource;
use App\Enums\ChangeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ENT-K05-05 */
class ChangeRequest extends Model
{
    use HasUuids;

    public const HISTORY_TYPE = 'CHANGE_REQUEST';

    protected $fillable = [
        'project_id', 'mom_id', 'change_description', 'source', 'change_type', 'is_formal_cr',
        'baseline_item_id', 'impact', 'analyzed_by', 'status', 'decision_note', 'decided_by',
        'decided_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source' => ChangeSource::class,
            'change_type' => ChangeType::class,
            'is_formal_cr' => 'boolean',
            'status' => ChangeRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function mom(): BelongsTo
    {
        return $this->belongsTo(Mom::class);
    }

    public function affectedTasks(): HasMany
    {
        return $this->hasMany(ChangeRequestTask::class);
    }
}