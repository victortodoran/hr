<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\Employee;

/** @phpstan-type ChainMember array{id: int, name: string, job_title: string|null, department: string|null, status: string, link: 'employee'|'directory'|null} */
final class ReportingChain
{
    /**
     * @return array{above: array<int, ChainMember>, employee: ChainMember, below: array<int, ChainMember>}
     */
    public static function of(Employee $employee, Employee $viewer): array
    {
        $above = $employee->reportingChain()->reverse()->values();
        $below = $employee->managedEmployees();
        $employee->loadMissing(['department', 'internalAssignment']);

        return [
            'above' => array_map(fn (Employee $member): array => self::member($member, $viewer), $above->all()),
            'employee' => self::member($employee, $viewer),
            'below' => array_map(fn (Employee $member): array => self::member($member, $viewer), $below->all()),
        ];
    }

    /**
     * @return ChainMember
     */
    private static function member(Employee $member, Employee $viewer): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'job_title' => $member->internalAssignment?->job_title,
            'department' => $member->department?->name, // @pest-mutate-ignore (every Employee has a Department; the nullsafe only satisfies the relation's nullable type)
            'status' => $member->employmentStatus()->value,
            'link' => match (true) {
                $viewer->can('view', $member) => 'employee',
                $viewer->can(Permission::ViewDirectory->value) => 'directory',
                default => null,
            },
        ];
    }
}
