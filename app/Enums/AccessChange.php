<?php

namespace App\Enums;

/**
 * The kinds of change to access the Access Log records.
 */
enum AccessChange: string
{
    case RoleCreated = 'role-created';
    case RoleEdited = 'role-edited';
    case RoleDeleted = 'role-deleted';
    case RoleGiven = 'role-given';
    case RoleTaken = 'role-taken';
}
