<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import TravelExpenseController from '@/actions/App/Http/Controllers/Travel/TravelExpenseController';
import TravelExpenseReceiptController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReceiptController';
import Callout from '@/components/Callout.vue';
import DatePicker from '@/components/DatePicker.vue';
import FileDrop from '@/components/FileDrop.vue';
import FormSheet from '@/components/FormSheet.vue';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateSpan } from '@/lib/dates';
import { travelExpenseCategories } from '@/lib/travel-expenses';
import type {
    TravelCurrency,
    TravelExpenseCategory,
    TravelExpenseTyped,
} from '@/types';

/**
 * Adding a Travel Expense, or, given one, editing it, in a sheet over the
 * Travel Request's page (rule L14). A new one always needs its receipt;
 * editing shows the current receipt, kept unless another file replaces
 * it. Editing a Declined one shows why it was declined at the top and,
 * above Save, that saving sends it back as Pending (X8). Once saved, the
 * page shows it and the sheet closes.
 */
const props = defineProps<{
    travelRequest: {
        id: number;
        destination: string;
        first_date: string;
        last_date: string;
    };
    /** The company currency, the amount's prefix. */
    currency: TravelCurrency;
    /** The latest date one can be paid on, as YYYY-MM-DD. */
    today: string;
    /** The Travel Expense to edit; none to add a new one. */
    travelExpense?: TravelExpenseTyped | null;
    /** The Employee's Manager, who decides the Travel Expense once saved. */
    manager?: string | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const FORM_ID = 'travel-expense';

const ACCEPTED_RECEIPTS =
    '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png';

const form = useForm<{
    paid_on: string;
    category: TravelExpenseCategory | '';
    amount: string;
    description: string;
    receipt: File | null;
}>({
    paid_on: '',
    category: '',
    amount: '',
    description: '',
    receipt: null,
});

const isEdit = computed(() => Boolean(props.travelExpense));

const isDeclined = computed(() => props.travelExpense?.state === 'declined');

const amountStep = computed(() =>
    props.currency.decimals === 0
        ? '1'
        : `0.${'0'.repeat(props.currency.decimals - 1)}1`,
);

const currentReceipt = computed(() =>
    props.travelExpense
        ? {
              name: props.travelExpense.receipt_name,
              url: TravelExpenseReceiptController.show.url(
                  props.travelExpense.id,
              ),
          }
        : null,
);

/** Starts each sheet with the Travel Expense being edited, or blank. */
watch(
    () => [open.value, props.travelExpense?.id] as const,
    ([isOpen]) => {
        if (!isOpen) {
            return;
        }

        form.defaults({
            paid_on: props.travelExpense?.paid_on ?? '',
            category: props.travelExpense?.category ?? '',
            amount: props.travelExpense?.amount ?? '',
            description: props.travelExpense?.description ?? '',
            receipt: null,
        });
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function save(): void {
    const options = { preserveScroll: true, preserveState: true };

    if (props.travelExpense) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            TravelExpenseController.update.url(props.travelExpense.id),
            options,
        );

        return;
    }

    form.transform((data) => data).submit(
        TravelExpenseController.store(props.travelRequest.id),
        options,
    );
}
</script>

<template>
    <FormSheet
        v-model:open="open"
        :title="isEdit ? 'Edit Travel Expense' : 'Add Travel Expense'"
        :description="`${travelRequest.destination} · ${formatDateSpan(travelRequest.first_date, travelRequest.last_date)}`"
    >
        <form :id="FORM_ID" class="flex flex-col gap-5" @submit.prevent="save">
            <Callout
                v-if="isDeclined && travelExpense?.decline_reason"
                tone="declined"
            >
                <span class="font-semibold"
                    >Declined<template v-if="travelExpense.declined_by">
                        by {{ travelExpense.declined_by }}</template
                    >:</span
                >
                “{{ travelExpense.decline_reason }}”
            </Callout>

            <div class="grid gap-5 sm:grid-cols-2 sm:gap-3">
                <div class="grid content-start gap-2">
                    <Label for="travel-expense-paid-on">Date paid</Label>
                    <DatePicker
                        id="travel-expense-paid-on"
                        v-model="form.paid_on"
                        :max="today"
                        required
                        :invalid="Boolean(form.errors.paid_on)"
                    />
                    <InputError :message="form.errors.paid_on" />
                </div>
                <div class="grid content-start gap-2">
                    <Label for="travel-expense-amount">Amount</Label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-body text-muted-foreground"
                            aria-hidden="true"
                            >{{ currency.code }}</span
                        >
                        <Input
                            id="travel-expense-amount"
                            v-model="form.amount"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            :step="amountStep"
                            required
                            class="tabular-nums"
                            :style="{
                                paddingLeft: `calc(${currency.code.length}ch + 1.25rem)`,
                            }"
                            :aria-invalid="Boolean(form.errors.amount)"
                        />
                    </div>
                    <InputError :message="form.errors.amount" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="travel-expense-category">Category</Label>
                <Select
                    name="category"
                    required
                    :model-value="form.category"
                    @update:model-value="
                        form.category = $event
                            ? ($event as TravelExpenseCategory)
                            : ''
                    "
                >
                    <SelectTrigger
                        id="travel-expense-category"
                        class="w-full"
                        :aria-invalid="Boolean(form.errors.category)"
                    >
                        <SelectValue placeholder="Choose a Category" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, category) in travelExpenseCategories"
                            :key="category"
                            :value="category"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.category" />
            </div>
            <div class="grid gap-2">
                <Label for="travel-expense-description">Description</Label>
                <Input
                    id="travel-expense-description"
                    v-model="form.description"
                    placeholder="Hotel, three nights"
                    maxlength="1000"
                    required
                    :aria-invalid="Boolean(form.errors.description)"
                />
                <InputError :message="form.errors.description" />
            </div>
            <div class="grid gap-2">
                <Label for="travel-expense-receipt">Receipt</Label>
                <FileDrop
                    id="travel-expense-receipt"
                    v-model="form.receipt"
                    :accept="ACCEPTED_RECEIPTS"
                    :max-mb="10"
                    noun="receipt"
                    :current="currentReceipt"
                    :invalid="Boolean(form.errors.receipt)"
                />
                <InputError :message="form.errors.receipt" />
            </div>

            <Callout v-if="isDeclined" tone="info">
                <template v-if="manager"
                    >Saving sends this Travel Expense back to {{ manager }} as
                    Pending.</template
                >
                <template v-else
                    >With no Manager, saving Approves this Travel Expense at
                    once.</template
                >
            </Callout>
        </form>

        <template #footer>
            <Button type="button" variant="ghost" @click="open = false"
                >Cancel</Button
            >
            <SubmitButton :form="FORM_ID" :processing="form.processing">{{
                isEdit ? 'Save Travel Expense' : 'Add Travel Expense'
            }}</SubmitButton>
        </template>
    </FormSheet>
</template>
