<?php

namespace Tests\Utils\Policies;

use Tests\Utils\Models\Team;

final class TeamPolicy
{
    public function onlyTeams(?Team $user): bool
    {
        return null !== $user;
    }
}
