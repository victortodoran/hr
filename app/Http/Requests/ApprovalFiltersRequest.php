<?php

namespace App\Http\Requests;

use App\Enums\ApprovalsTab;

/**
 * The Approvals page's query: the open tab and the filters on the Pending
 * Work Entries waiting for approval.
 */
class ApprovalFiltersRequest extends WorkEntryFiltersRequest
{
    /**
     * The tab asked for: Time Off when asked for, Work Entries for anything
     * else or no tab.
     */
    public function tab(): ApprovalsTab
    {
        return ApprovalsTab::tryFrom($this->string('tab')->value()) ?? ApprovalsTab::WorkEntries;
    }
}
