<?php

namespace App\Http\Requests\WorkEntries;

use App\Enums\ApprovalsTab;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Approvals page's query: the open tab and the Employee and Project
 * asked for on the Pending Work Entries waiting for approval. Approvals work
 * through one Employee at a time, so there is no date range.
 */
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

    /**
     * The Employee and Project asked for, null where none is.
     *
     * @return array{employee: int|null, project: int|null}
     */
    public function filters(): array
    {
        return [
            'employee' => $this->filled('employee') ? $this->integer('employee') : null,
            'project' => $this->filled('project') ? $this->integer('project') : null,
        ];
    }

    /**
     * The tab asked for: Time Off when asked for, Work Entries for anything
     * else or no tab.
     */
    public function tab(): ApprovalsTab
    {
        return ApprovalsTab::tryFrom($this->string('tab')->value()) ?? ApprovalsTab::WorkEntries;
    }
}
