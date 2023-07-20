<?php

namespace Tests\Utils\Models\User;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

final class UserBuilder extends Builder
{
    /**
     * @param  array{company: string} $args
     */
    public function companyName(array $args): self
    {
        return $this->where(fn (self $builder) => $builder
            ->whereHas('company', static fn (EloquentBuilder $q): EloquentBuilder => $q
            ->where('name', $args['company'])));
    }

    public function named(): self
    {
        return $this->whereNotNull('name');
    }
}
