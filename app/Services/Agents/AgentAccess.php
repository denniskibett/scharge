<?php

namespace App\Services\Agents;

use App\Models\PropertyAgent;
use Illuminate\Support\Facades\Schema;

/**
 * Who is an agent and which properties they may work on. An agent is anyone with the "agent" role, or the admin of
 * an agency business (companies.kind = agency). They see the properties landlords gave their agency (property_agents).
 */
class AgentAccess
{
    public static function enabled(): bool
    {
        static $on;

        return $on ??= Schema::hasTable('property_agents');
    }

    public static function isAgent($user): bool
    {
        if (! $user || ! $user->company_id) {
            return false;
        }

        return $user->hasRole('agent') || ($user->hasRole('admin') && $user->company?->kind === 'agency');
    }

    /** @return int[] property ids this agent's agency may work on */
    public static function propertyIds($user): array
    {
        if (! self::enabled() || ! self::isAgent($user)) {
            return [];
        }

        return PropertyAgent::active()->where('agency_id', $user->company_id)->pluck('property_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public static function grant($user, int $propertyId): ?PropertyAgent
    {
        if (! self::enabled() || ! self::isAgent($user)) {
            return null;
        }

        return PropertyAgent::active()->where('agency_id', $user->company_id)->where('property_id', $propertyId)->first();
    }
}
