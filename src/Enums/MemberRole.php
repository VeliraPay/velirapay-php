<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The roles a person can have in an account. Owner only appears in answers: invitations and role changes give one of the other four.
 */
enum MemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Finance = 'finance';
    case Support = 'support';
    case Viewer = 'viewer';

    /**
     * Get the roles an invitation or a role change can give.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Finance, self::Support, self::Viewer];
    }
}
