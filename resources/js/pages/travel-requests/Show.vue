<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import TravelRequestApprovalController from '@/actions/App/Http/Controllers/Travel/TravelRequestApprovalController';
import TravelRequestCancellationController from '@/actions/App/Http/Controllers/Travel/TravelRequestCancellationController';
import TravelRequestDeclineController from '@/actions/App/Http/Controllers/Travel/TravelRequestDeclineController';
import DeclineSelectedDialog from '@/components/approvals/DeclineSelectedDialog.vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TravelExpenseList from '@/components/travel-requests/TravelExpenseList.vue';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import { show as showEmployee } from '@/routes/employees';
import { index, show } from '@/routes/travel-requests';
import type { BreadcrumbItem, Choice, TravelRequestRecord } from '@/types';

/**
 * One Travel Request in full with its Travel Expenses. The Employee
 * themselves may cancel it and record and change its Travel Expenses; their
 * effective Manager approves or declines it, and its Pending Travel
 * Expenses, here as on the Approvals page; anyone else sees it.
 */
const props = defineProps<{
    travelRequest: TravelRequestRecord;
    employee: Choice;
    /** Whether the Travel Request is the viewer's own. */
    isOwn: boolean;
    /** Whether the viewer may open the Employee's record, where the breadcrumb leads. */
    canViewEmployee: boolean;
}>();

/** Travel › destination for the Employee themselves; Employees › name › destination for anyone else. */
const leadingBreadcrumbs: BreadcrumbItem[] = props.isOwn
    ? [{ title: 'Travel', href: index() }]
    : [
          { title: 'Employees' },
          {
              title: props.employee.name,
              href: props.canViewEmployee
                  ? showEmployee(props.employee.id)
                  : undefined,
          },
      ];

setLayoutProps({
    breadcrumbs: [
        ...leadingBreadcrumbs,
        {
            title: props.travelRequest.destination,
            href: show(props.travelRequest.id),
        },
    ],
});
</script>

<template>
    <Head :title="travelRequest.destination" />

    <PageContainer width="wide">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ travelRequest.destination }}
                    <span class="font-normal text-muted-foreground"
                        >·
                        {{
                            formatDateRange(
                                travelRequest.first_date,
                                travelRequest.last_date,
                            )
                        }}</span
                    >
                </h1>
                <div
                    class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
                >
                    <StatusBadge :state="travelRequest.state" />
                    <span>{{ formatDays(travelRequest.days) }}</span>
                </div>
            </div>
            <div
                v-if="travelRequest.can_decide"
                class="flex flex-wrap items-center gap-2"
            >
                <Form
                    v-bind="
                        TravelRequestApprovalController.store.form(
                            travelRequest.id,
                        )
                    "
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <SubmitButton :processing="processing"
                        >Approve</SubmitButton
                    >
                </Form>
                <DeclineSelectedDialog
                    :action="
                        TravelRequestDeclineController.store(travelRequest.id)
                    "
                    :noun="{ one: 'Travel Request', many: 'Travel Requests' }"
                    description="It stays on record as Declined, and the Employee sees the reason on their Travel page."
                    placeholder="The release is that week"
                />
            </div>
            <ConfirmDeleteDialog
                v-if="travelRequest.can_cancel"
                :form="
                    TravelRequestCancellationController.store.form(
                        travelRequest.id,
                    )
                "
                title="Cancel this Travel Request?"
                description="It stays on record as Cancelled, and frees its days for other travel or Time Off."
                confirm-label="Cancel travel"
                dismiss-label="Keep it"
            >
                <template #trigger>
                    <Button variant="outline" class="text-destructive"
                        >Cancel travel</Button
                    >
                </template>
            </ConfirmDeleteDialog>
        </header>

        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Project</dt>
                <dd>{{ travelRequest.project }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Client</dt>
                <dd>{{ travelRequest.client ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-muted-foreground">Purpose</dt>
                <dd>{{ travelRequest.purpose }}</dd>
            </div>
            <div v-if="travelRequest.decided_by !== null" class="sm:col-span-2">
                <dt class="text-muted-foreground">
                    {{
                        travelRequest.state === 'declined'
                            ? 'Declined by'
                            : 'Approved by'
                    }}
                </dt>
                <dd>{{ travelRequest.decided_by }}</dd>
            </div>
            <div
                v-if="travelRequest.decline_reason !== null"
                class="sm:col-span-2"
            >
                <dt class="text-muted-foreground">Why it was declined</dt>
                <dd>“{{ travelRequest.decline_reason }}”</dd>
            </div>
        </dl>

        <TravelExpenseList
            v-if="travelRequest.expenses !== null"
            :travel-request-id="travelRequest.id"
            :expenses="travelRequest.expenses"
        />
    </PageContainer>
</template>
