<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** ENT-K05-08. Append-only: write it through App\Services\StatusHistoryRecorder. */
class StatusHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['entity_type', 'entity_id', 'from_status', 'to_status', 'changed_by', 'changed_at', 'note'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
