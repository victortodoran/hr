<?php

namespace App\Enums;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

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
     * Whether this Scope can cover anyone besides the acting Employee.
     */
    public function coversOthers(): bool
    {
        return $this !== self::Own;
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

    /**
     * Limit a query of Employees to those this Scope, held by the acting
     * Employee, covers: the query counterpart of covers(). Each Scope adds
     * a condition, even everyone, so Scopes can be combined with orWhere.
     *
     * @param  Builder<Employee>  $employees
     */
    public function limit(Builder $employees, Employee $actor): void
    {
        match ($this) {
            self::Own => $employees->whereKey($actor->id),
            self::Reports => $employees->reportsOf($actor),
            self::Everyone => $employees->whereRaw('true'),
        };
    }
}
