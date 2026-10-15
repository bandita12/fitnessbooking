<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant scoping for studio-owned resources.
 *
 * Controllers resolve through these scopes with findOrFail(), so cross-tenant
 * access surfaces as ModelNotFoundException (404) instead of 403 and never
 * leaks the existence of foreign data.
 */
trait ScopedByStudio
{
    public function scopeForTenant(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::Admin || $user->role === UserRole::Customer) {
            return $query;
        }

        if ($user->role === UserRole::Trainer) {
            return $query->whereIn(
                $this->getTable().'.'.$this->tenantColumn(),
                $user->studios()->pluck('studios.id')
            );
        }

        return $query->where($this->getTable().'.'.$this->tenantColumn(), $user->studio_id);
    }

    protected function tenantColumn(): string
    {
        return 'studio_id';
    }
}
