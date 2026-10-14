<?php

namespace App\Models;

use App\Enums\ParticipantType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ENT-K05-02 */
class MeetingParticipant extends Model
{
    use HasUuids;

    protected $fillable = ['meeting_id', 'participant_type', 'account_id', 'display_name', 'attended'];

    protected function casts(): array
    {
        return [
            'participant_type' => ParticipantType::class,
            'attended' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
