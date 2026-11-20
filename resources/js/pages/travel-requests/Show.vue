<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import TravelRequestApprovalController from '@/actions/App/Http/Controllers/Travel/TravelRequestApprovalController';
import TravelRequestCancellationController from '@/actions/App/Http/Controllers/Travel/TravelRequestCancellationController';
import TravelRequestDeclineController from '@/actions/App/Http/Controllers/Travel/TravelRequestDeclineController';
import DeclineSelectedDialog from '@/components/approvals/DeclineSelectedDialog.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import PageContainer from '@/components/PageContainer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TravelExpenseList from '@/components/travel-requests/TravelExpenseList.vue';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import { show as showEmployee } from '@/routes/employees';
import { index as travelRequestList } from '@/routes/travel-request-list';
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
    /** Whether the viewer may open the Travel Requests page, where the breadcrumbs lead first. */
    canViewTravelRequests: boolean;
}>();

/**
 * Travel › destination for the Employee themselves; Travel Requests ›
 * name · destination for a viewer who may open that page; Employees › name ›
 * destination for anyone else.
 */
const leadingBreadcrumbs: BreadcrumbItem[] = props.isOwn
    ? [{ title: 'Travel', href: index() }]
    : props.canViewTravelRequests
      ? [{ title: 'Travel Requests', href: travelRequestList() }]
      : [
            { title: 'Employees' },
            {
                title: props.employee.name,
                href: props.canViewEmployee
                    ? showEmployee(props.employee.id)
                    : undefined,
            },
        ];

const title =
    !props.isOwn && props.canViewTravelRequests
        ? `${props.employee.name} · ${props.travelRequest.destination}`
        : props.travelRequest.destination;

setLayoutProps({
    breadcrumbs: [
        ...leadingBreadcrumbs,
        { title, href: show(props.travelRequest.id) },
    ],
});
</script>

<template>
    <Head :title="travelRequest.destination" />

    <PageContainer width="wide">
        <Heading :title="travelRequest.destination">
            <template #description>
                <StatusBadge :state="travelRequest.state" />
                <span>{{
                    formatDateRange(
                        travelRequest.first_date,
                        travelRequest.last_date,
                    )
                }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ formatDays(travelRequest.days) }}</span>
            </template>
            <template
                v-if="travelRequest.can_decide || travelRequest.can_cancel"
                #actions
            >
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
                            TravelRequestDeclineController.store(
                                travelRequest.id,
                            )
                        "
                        :noun="{
                            one: 'Travel Request',
                            many: 'Travel Requests',
                        }"
                        description="It stays on record as Declined, and the Employee sees the reason on their Travel page."
                        placeholder="The release is that week"
                    />
                </div>
                <ConfirmDialog
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
                        <Button variant="secondary">Cancel travel</Button>
                    </template>
                </ConfirmDialog>
            </template>
        </Heading>

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
