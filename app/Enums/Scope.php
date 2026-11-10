<?php

namespace App\Enums;

use App\Models\Employee;

/**
 * Which Employees a Permission about Employees applies to.
 */
enum Scope: string
{
    case Own = 'own';
    case Reports = 'reports';
    case Everyone = 'everyone';

    /**
     * The widest of the given Scopes (everyone > reports > own), or null when
     * there are none, meaning no Role grants the Permission.
     *
     * @param  iterable<Scope>  $scopes
     */
    public static function widest(iterable $scopes): ?self
    {
        $given = collect($scopes);

        return collect([self::Everyone, self::Reports, self::Own])
            ->first(fn (self $candidate): bool => $given->contains($candidate));
    }

    /**
     * Whether this Scope, held by the acting Employee, covers the target.
     * Reports are everyone below the acting Employee in the reporting line.
     */
    public function covers(Employee $actor, Employee $target): bool
    {
        return match ($this) {
            self::Own => $actor->is($target),
            self::Reports => $actor->hasInReports($target),
            self::Everyone => true,
        };
    }
}
