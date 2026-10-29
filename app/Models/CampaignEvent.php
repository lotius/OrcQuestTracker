<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ulid', 'type', 'slot', 'who', 'hero_id', 'text', 'source', 'coalesce', 'happened_at'])]
class CampaignEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['happened_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** The shape the History tab and the save endpoint use. */
    public function toHistoryEntry(): array
    {
        return array_filter([
            'id' => $this->ulid,
            'at' => $this->happened_at->toIso8601String(),
            'type' => $this->type,
            'slot' => $this->slot,
            'who' => $this->who,
            'hero_id' => $this->hero_id,
            'text' => $this->text,
            'source' => $this->source,
            'coalesce' => $this->coalesce,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
