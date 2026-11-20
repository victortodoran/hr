<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Ellipsis, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import TravelRequestApprovalController from '@/actions/App/Http/Controllers/Travel/TravelRequestApprovalController';
import TravelRequestCancellationController from '@/actions/App/Http/Controllers/Travel/TravelRequestCancellationController';
import TravelRequestDeclineController from '@/actions/App/Http/Controllers/Travel/TravelRequestDeclineController';
import Callout from '@/components/Callout.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DecideDialog from '@/components/DecideDialog.vue';
import FactList from '@/components/FactList.vue';
import PageContainer from '@/components/PageContainer.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatStrip from '@/components/StatStrip.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TravelExpenseList from '@/components/travel-requests/TravelExpenseList.vue';
import TravelExpenseSheet from '@/components/travel-requests/TravelExpenseSheet.vue';
import { Button } from '@/components/ui/button';
import { useOverlayRoute } from '@/composables/useOverlayRoute';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDateRange } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import { show as showEmployee } from '@/routes/employees';
import { index as travelRequestList } from '@/routes/travel-request-list';
import { index, show } from '@/routes/travel-requests';
import { create as createExpense } from '@/routes/travel-requests/expenses';
import type {
    BreadcrumbItem,
    Choice,
    TravelCurrency,
    TravelExpenseTyped,
    TravelRequestRecord,
} from '@/types';

/**
 * One Travel Request on the detail template (rule L12): its destination,
 * StatusBadge and dates in the header; a Declined one's reason in a
 * Callout; the totals of its Travel Expenses in a StatStrip, Declined ones
 * not counted (S12); then its Travel Expenses beside its Details.
 *
 * The Employee themselves may cancel it from the ⋯ menu (X4) and add,
 * edit and delete its Travel Expenses; Add and Edit Travel Expense open a
 * sheet over the page, whose URLs render this page with `expenseSheet`
 * set (L14). Their effective Manager approves or declines it, and its
 * Pending Travel Expenses, here as on the Approvals page; anyone else
 * sees it.
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
    /** Which Travel Expense sheet is open, if any. */
    expenseSheet?: 'add' | 'edit';
    /** The company currency, sent with the sheet. */
    currency?: TravelCurrency;
    /** The latest date paid, sent with the sheet. */
    today?: string;
    /** The Travel Expense being edited, sent with the Edit sheet. */
    travelExpense?: TravelExpenseTyped;
    /** The Employee's effective Manager, or null with none, sent with the Edit sheet. */
    manager?: string | null;
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

const where = computed(() =>
    [props.travelRequest.project, props.travelRequest.client]
        .filter(Boolean)
        .join(', '),
);

const facts = computed(() => [
    { label: 'Project', value: props.travelRequest.project },
    { label: 'Client', value: props.travelRequest.client },
    { label: 'Purpose', value: props.travelRequest.purpose },
    { label: 'Decided by', value: props.travelRequest.decided_by },
]);

const stats = computed(() => {
    const expenses = props.travelRequest.expenses;

    return expenses === null
        ? []
        : [
              { label: 'Entered', value: expenses.total },
              {
                  label: 'Approved',
                  value: expenses.approved_total,
                  state: 'approved' as const,
              },
              {
                  label: 'Pending',
                  value: expenses.pending_total,
                  state: 'pending' as const,
              },
              {
                  label: 'Declined, not counted',
                  value: expenses.declined_total,
                  state: 'declined' as const,
                  muted: true,
              },
          ];
});

const canAddExpense = computed(
    () => props.travelRequest.expenses?.can_create === true,
);

const cancelOpen = ref(false);

/** The ⋯ button, anchoring its menu past the Tooltip around it. */
const moreTrigger = ref<InstanceType<typeof Button> | null>(null);

const { open: sheetOpen, setOpen: setSheetOpen } = useOverlayRoute(
    () => props.expenseSheet !== undefined,
    () => show(props.travelRequest.id).url,
);

/**
 * What the sheet last opened with, kept while it closes, once the page's
 * own URL no longer sends it.
 */
const sheet = ref(sheetProps());

function sheetProps() {
    return props.currency
        ? {
              currency: props.currency,
              today: props.today ?? '',
              travelExpense:
                  props.expenseSheet === 'edit'
                      ? (props.travelExpense ?? null)
                      : null,
              manager: props.manager ?? null,
          }
        : null;
}

watch(
    () => [props.expenseSheet, props.travelExpense] as const,
    ([expenseSheet]) => {
        if (expenseSheet !== undefined) {
            sheet.value = sheetProps();
        }
    },
);
</script>

<template>
    <Head :title="travelRequest.destination" />

    <PageContainer width="full">
        <PageHeader :title="title">
            <template #status>
                <StatusBadge :state="travelRequest.state" />
            </template>
            <template #description>
                <span class="tabular-nums">{{
                    formatDateRange(
                        travelRequest.first_date,
                        travelRequest.last_date,
                    )
                }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ formatDays(travelRequest.days) }}</span>
                <template v-if="where">
                    <span aria-hidden="true">·</span>
                    <span>{{ where }}</span>
                </template>
            </template>
            <template
                v-if="
                    travelRequest.can_decide ||
                    canAddExpense ||
                    travelRequest.can_cancel
                "
                #actions
            >
                <template v-if="travelRequest.can_decide">
                    <Form
                        v-bind="
                            TravelRequestApprovalController.store.form(
                                travelRequest.id,
                            )
                        "
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <SubmitButton :processing="processing" class="w-full"
                            >Approve Travel Request</SubmitButton
                        >
                    </Form>
                    <DecideDialog
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
                </template>
                <Button v-if="canAddExpense" as-child>
                    <Link
                        :href="createExpense(travelRequest.id)"
                        preserve-scroll
                        preserve-state
                        ><Plus />Add Travel Expense</Link
                    >
                </Button>
                <DropdownMenu v-if="travelRequest.can_cancel">
                    <Tooltip>
                        <DropdownMenuTrigger as-child>
                            <TooltipTrigger as-child>
                                <Button
                                    ref="moreTrigger"
                                    variant="secondary"
                                    size="icon"
                                    class="max-sm:size-11 max-sm:flex-none!"
                                    aria-label="More for this Travel Request"
                                >
                                    <Ellipsis />
                                </Button>
                            </TooltipTrigger>
                        </DropdownMenuTrigger>
                        <TooltipContent
                            >More for this Travel Request</TooltipContent
                        >
                    </Tooltip>
                    <DropdownMenuContent
                        align="end"
                        :reference="moreTrigger?.$el as HTMLElement | undefined"
                    >
                        <DropdownMenuItem @select="cancelOpen = true"
                            >Cancel Travel Request…</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </PageHeader>

        <Callout
            v-if="travelRequest.decline_reason !== null"
            tone="declined"
            data-test="decline-reason"
        >
            <span class="font-semibold"
                >Declined<template v-if="travelRequest.decided_by">
                    by {{ travelRequest.decided_by }}</template
                >:</span
            >
            “{{ travelRequest.decline_reason }}”
        </Callout>

        <StatStrip v-if="stats.length > 0" :stats="stats" />

        <div
            class="grid gap-8"
            :class="
                travelRequest.expenses !== null
                    ? 'lg:grid-cols-[minmax(0,1fr)_300px]'
                    : ''
            "
        >
            <TravelExpenseList
                v-if="travelRequest.expenses !== null"
                :travel-request-id="travelRequest.id"
                :expenses="travelRequest.expenses"
            />
            <section class="flex min-w-0 flex-col gap-3">
                <h2 class="text-section">Details</h2>
                <FactList :facts="facts" />
            </section>
        </div>

        <ConfirmDialog
            v-if="travelRequest.can_cancel"
            v-model:open="cancelOpen"
            without-trigger
            :form="
                TravelRequestCancellationController.store.form(travelRequest.id)
            "
            title="Cancel Travel Request"
            description="It stays on record as Cancelled, and frees its days for other travel or Time Off."
            confirm-label="Cancel Travel Request"
            dismiss-label="Keep it"
        />

        <TravelExpenseSheet
            v-if="sheet"
            :open="sheetOpen"
            :travel-request="travelRequest"
            :currency="sheet.currency"
            :today="sheet.today"
            :travel-expense="sheet.travelExpense"
            :manager="sheet.manager"
            @update:open="setSheetOpen"
        />
    </PageContainer>
</template>
