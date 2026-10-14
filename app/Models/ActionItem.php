<?php

namespace App\Models;

use App\Enums\ActionItemStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ENT-K05-04 */
class ActionItem extends Model
{
    use HasUuids;

    public const HISTORY_TYPE = 'ACTION_ITEM';

    protected $fillable = ['mom_id', 'description', 'pic_account_id', 'due_date', 'status', 'completed_at'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'status' => ActionItemStatus::class,
        ];
    }

    public function mom(): BelongsTo
    {
        return $this->belongsTo(Mom::class);
    }
}
