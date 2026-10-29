<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    /** Campaigns are private to the account that owns them. */
    public function manage(User $user, Campaign $campaign): bool
    {
        return $campaign->user_id === $user->id;
    }
}
