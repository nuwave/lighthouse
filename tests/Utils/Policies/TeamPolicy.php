<?php

namespace Tests\Utils\Policies;

use Illuminate\Auth\Access\Response;
use Tests\Utils\Models\Team;

final class TeamPolicy
{
    public function onlyTeams($user): bool
    {
        return $user instanceof Team;
    }
}
