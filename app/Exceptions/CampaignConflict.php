<?php

namespace App\Exceptions;

use RuntimeException;

/** The campaign was saved from another tab or device after this copy was loaded. */
class CampaignConflict extends RuntimeException
{
    public function __construct(public readonly int $currentVersion)
    {
        parent::__construct('This campaign was changed somewhere else since it was opened.');
    }
}
