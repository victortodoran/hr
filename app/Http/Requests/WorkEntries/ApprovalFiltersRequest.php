<?php

namespace App\Http\Requests\WorkEntries;

use App\Enums\ApprovalsTab;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Approvals work through one Employee at a time, so there is no date range. */
class ApprovalFiltersRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee' => ['nullable', 'integer'],
            'project' => ['nullable', 'integer'],
        ];
    }

    /** @return array{employee: int|null, project: int|null} */
    public function filters(): array
    {
        return [
            'employee' => $this->filled('employee') ? $this->integer('employee') : null,
            'project' => $this->filled('project') ? $this->integer('project') : null,
        ];
    }

    public function tab(): ApprovalsTab
    {
        return ApprovalsTab::tryFrom($this->string('tab')->value()) ?? ApprovalsTab::WorkEntries;
    }
}
