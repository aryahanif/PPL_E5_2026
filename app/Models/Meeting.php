<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** ENT-K05-01 */
class Meeting extends Model
{
    use HasUuids;

    public const HISTORY_TYPE = 'MEETING';

    protected $fillable = ['project_id', 'title', 'scheduled_at', 'location', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status' => MeetingStatus::class,
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function mom(): HasOne
    {
        return $this->hasOne(Mom::class);
    }
}
