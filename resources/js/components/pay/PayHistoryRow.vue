<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import PayEntryController from '@/actions/App/Http/Controllers/Assignments/PayEntryController';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import RowActions from '@/components/RowActions.vue';
import SegmentedControl from '@/components/SegmentedControl.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatDateRange } from '@/lib/dates';
import { formatPay } from '@/lib/pay';
import type {
    AssignmentRecord,
    PayChangeState,
    PayEntryRecord,
    PayKind,
    StatusBadgeState,
} from '@/types';

/**
 * An Assignment's Pay history, opened in place under its row (rules K2,
 * S9): each Pay change newest first on a timeline, with the days it
 * applies and whether it is Upcoming, In effect or Past. Those who may
 * manage Pay remove a change from its ⋯ menu, after confirming, and add
 * one with the inline form under the timeline. A change can't be edited:
 * a correction is a Remove and an Add.
 */
const props = defineProps<{
    assignment: AssignmentRecord & { pay: PayEntryRecord[] };
    /** The company currency, given only to those who may manage Pay. */
    currency?: string;
}>();

const badgeStates: Record<PayChangeState, StatusBadgeState> = {
    upcoming: 'upcoming',
    in_effect: 'in-effect',
    past: 'past',
};

const dotClasses: Record<PayChangeState, string> = {
    upcoming: 'border-dashed border-status-upcoming-icon bg-background',
    in_effect: 'border-status-approved-icon bg-status-approved-icon',
    past: 'border-border-strong bg-background',
};

const kinds: { value: PayKind; label: string }[] = [
    { value: 'fixed', label: 'Fixed, per month' },
    { value: 'hourly', label: 'Hourly' },
];

const fieldId = (field: string): string =>
    `pay-${field}-${props.assignment.id}`;

/** The days a change applies, such as "Aug 20, 2026 – Jan 19, 2027" (C9). */
function applies(entry: PayEntryRecord): string {
    return entry.until
        ? formatDateRange(entry.effective_from, entry.until)
        : `from ${formatDate(entry.effective_from)}`;
}

/** Names a change for its buttons, such as "the Pay change from Aug 20, 2026". */
function describe(entry: PayEntryRecord): string {
    return `the Pay change from ${formatDate(entry.effective_from)}`;
}

const form = useForm<{ kind: PayKind; amount: string; effective_from: string }>(
    {
        kind: 'fixed',
        amount: '',
        effective_from: '',
    },
);

function add(): void {
    form.submit(PayEntryController.store(props.assignment.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

/** The change the Remove dialog asks about. */
const removing = ref<PayEntryRecord | null>(null);
const removeOpen = ref(false);

function askToRemove(entry: PayEntryRecord): void {
    removing.value = entry;
    removeOpen.value = true;
}
</script>

<template>
    <div
        data-slot="pay-history"
        :data-test="`pay-history-${assignment.id}`"
        class="flex flex-col gap-3 rounded-md bg-surface px-4 py-3"
    >
        <ol
            v-if="assignment.pay.length > 0"
            class="flex flex-col"
            :aria-label="`Pay history on ${assignment.project.name}`"
        >
            <li
                v-for="(entry, index) in assignment.pay"
                :key="entry.id"
                class="group/row relative grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 py-1.5 pl-6 max-sm:min-h-11 max-sm:border-t sm:grid-cols-[minmax(0,14rem)_minmax(0,16rem)_minmax(0,1fr)_2.5rem]"
                :class="entry.state === 'past' && 'text-muted-foreground'"
                data-test="pay-change"
            >
                <span
                    v-if="index < assignment.pay.length - 1"
                    class="absolute top-1/2 bottom-[-50%] left-[5px] w-px bg-border-strong max-sm:hidden"
                    aria-hidden="true"
                />
                <span
                    class="absolute top-1/2 left-0 size-[11px] -translate-y-1/2 rounded-full border-2"
                    :class="dotClasses[entry.state]"
                    aria-hidden="true"
                />
                <div class="min-w-0 sm:contents">
                    <span
                        class="tabular-nums"
                        :class="
                            entry.state === 'past'
                                ? 'font-medium'
                                : 'font-semibold'
                        "
                        >{{ formatPay(entry) }}</span
                    >
                    <span
                        class="block text-meta text-muted-foreground tabular-nums"
                        >{{ applies(entry) }}</span
                    >
                </div>
                <div class="flex items-center justify-end gap-1 sm:contents">
                    <StatusBadge :state="badgeStates[entry.state]" />
                    <RowActions v-if="currency" :label="describe(entry)">
                        <template #menu>
                            <DropdownMenuItem
                                variant="destructive"
                                @select="askToRemove(entry)"
                                >Remove…</DropdownMenuItem
                            >
                        </template>
                    </RowActions>
                    <span v-else class="max-sm:hidden" />
                </div>
            </li>
        </ol>
        <p v-else class="text-meta text-muted-foreground">No Pay yet.</p>

        <form
            v-if="currency"
            class="flex flex-col gap-3 border-t border-dashed border-border-strong pt-3 sm:flex-row sm:flex-wrap sm:items-start"
            :aria-label="`Add a Pay change on ${assignment.project.name}`"
            @submit.prevent="add"
        >
            <div class="grid gap-1.5">
                <span class="text-meta font-medium" aria-hidden="true"
                    >Kind</span
                >
                <SegmentedControl
                    v-model="form.kind"
                    :options="kinds"
                    label="Kind"
                    size="sm"
                />
                <InputError :message="form.errors.kind" />
            </div>
            <div class="grid gap-1.5">
                <Label :for="fieldId('amount')">Amount ({{ currency }})</Label>
                <Input
                    :id="fieldId('amount')"
                    v-model="form.amount"
                    inputmode="decimal"
                    placeholder="e.g. 4500.00"
                    class="tabular-nums max-sm:h-11 sm:w-36"
                    required
                    :aria-invalid="Boolean(form.errors.amount) || undefined"
                />
                <InputError :message="form.errors.amount" />
            </div>
            <div class="grid gap-1.5">
                <Label :for="fieldId('effective-from')"
                    >Takes effect from</Label
                >
                <DatePicker
                    :id="fieldId('effective-from')"
                    v-model="form.effective_from"
                    :min="assignment.start_date"
                    :max="assignment.end_date ?? undefined"
                    required
                    :invalid="Boolean(form.errors.effective_from)"
                    class="sm:w-48"
                />
                <InputError :message="form.errors.effective_from" />
            </div>
            <div class="grid gap-1.5 sm:pt-[1.625rem]">
                <SubmitButton
                    :processing="form.processing"
                    variant="secondary"
                    class="max-sm:h-11 max-sm:w-full"
                    ><Plus />Add Pay change</SubmitButton
                >
            </div>
            <p class="text-caption text-muted-foreground sm:basis-full">
                Each Pay applies from its date until the next one.
            </p>
        </form>

        <ConfirmDeleteDialog
            v-if="removing"
            v-model:open="removeOpen"
            without-trigger
            :form="
                PayEntryController.destroy.form([assignment.id, removing.id])
            "
            :title="`Remove the Pay change from ${formatDate(removing.effective_from)}?`"
            :description="`${formatPay(removing)} on ${assignment.project.name} is removed, and the Pay before it applies until the next change. This can't be undone.`"
            confirm-label="Remove Pay change"
        />
    </div>
</template>
