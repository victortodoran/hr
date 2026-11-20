<?php

namespace App\Actions\Company;

use App\Models\Department;
use Illuminate\Validation\ValidationException;

class DeleteDepartment
{
    /** @throws ValidationException */
    public function delete(Department $department): void
    {
        if ($department->employees()->exists()) {
            throw ValidationException::withMessages([
                'department' => __(':department still has Employees. Move them to another Department before deleting it.', [
                    'department' => $department->name,
                ]),
            ]);
        }

        $department->delete();
    }
}
