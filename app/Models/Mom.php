<?php

namespace App\Models;

use App\Enums\MomStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ENT-K05-03 */
class Mom extends Model
{
    use HasUuids;

    public const HISTORY_TYPE = 'MOM';

    protected $fillable = [
        'meeting_id', 'meeting_date', 'discussion', 'decision', 'change_summary',
        'status', 'written_by', 'reviewed_by', 'review_note', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'finalized_at' => 'datetime',
            'status' => MomStatus::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }
}
