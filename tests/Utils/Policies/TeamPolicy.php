<?php declare(strict_types=1);

namespace Tests\Utils\Policies;

use Tests\Utils\Models\Team;

final class TeamPolicy
{
    public function onlyTeams(?Team $user): bool
    {
        return $user !== null;
    }
}
