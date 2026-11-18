<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import TravelExpenseController from '@/actions/App/Http/Controllers/Travel/TravelExpenseController';
import TravelExpenseReceiptController from '@/actions/App/Http/Controllers/Travel/TravelExpenseReceiptController';
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
import { travelExpenseCategories } from '@/lib/travel-expenses';
import { show } from '@/routes/travel-requests';
import type {
    TravelCurrency,
    TravelExpenseCategory,
    TravelExpenseTyped,
} from '@/types';

/**
 * Records a Travel Expense on a Travel Request, or, given one, changes it.
 * A new one always needs its receipt; a change shows the current receipt
 * and keeps it unless a new file is chosen to replace it. Saving and
 * cancelling both lead back to the Travel Request's page.
 */
const props = defineProps<{
    today: string;
    currency: TravelCurrency;
    /** The Travel Request the Travel Expense is on. */
    travelRequestId: number;
    /** The Travel Expense to change; none to record a new one. */
    expense?: TravelExpenseTyped;
}>();

const form = useForm<{
    paid_on: string;
    category: TravelExpenseCategory | '';
    amount: string;
    description: string;
    receipt: File | null;
}>({
    paid_on: props.expense?.paid_on ?? '',
    category: props.expense?.category ?? '',
    amount: props.expense?.amount ?? '',
    description: props.expense?.description ?? '',
    receipt: null,
});

const isChange = computed(() => props.expense !== undefined);
const amountStep = computed(() =>
    props.currency.decimals === 0
        ? '1'
        : `0.${'0'.repeat(props.currency.decimals - 1)}1`,
);

function chooseReceipt(event: Event): void {
    form.receipt = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function save(): void {
    if (props.expense) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            TravelExpenseController.update.url(props.expense.id),
        );

        return;
    }

    form.submit(TravelExpenseController.store(props.travelRequestId));
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="travel-expense-paid-on">Date paid</Label>
                <Input
                    id="travel-expense-paid-on"
                    v-model="form.paid_on"
                    type="date"
                    :max="today"
                    required
                />
                <InputError :message="form.errors.paid_on" />
            </div>
            <div class="grid gap-2">
                <Label for="travel-expense-amount"
                    >Amount ({{ currency.code }})</Label
                >
                <Input
                    id="travel-expense-amount"
                    v-model="form.amount"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    :step="amountStep"
                    required
                />
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
                <SelectTrigger id="travel-expense-category" class="w-full">
                    <SelectValue placeholder="Choose a category" />
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
            />
            <InputError :message="form.errors.description" />
        </div>
        <div v-if="expense" class="grid gap-2 text-sm">
            <p class="font-medium">Current receipt</p>
            <a
                :href="TravelExpenseReceiptController.show.url(expense.id)"
                target="_blank"
                rel="noopener"
                class="w-fit underline underline-offset-4"
                >Open the receipt</a
            >
        </div>
        <div class="grid gap-2">
            <Label for="travel-expense-receipt">{{
                isChange ? 'Replace receipt' : 'Receipt'
            }}</Label>
            <input
                id="travel-expense-receipt"
                type="file"
                accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                :required="!isChange"
                class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                @change="chooseReceipt"
            />
            <p class="text-xs text-muted-foreground">
                A JPG, PNG or PDF of up to 10 MB.<template v-if="isChange">
                    Leave it empty to keep the current receipt.</template
                >
            </p>
            <InputError :message="form.errors.receipt" />
        </div>

        <div class="flex gap-2">
            <SubmitButton :processing="form.processing">{{
                isChange ? 'Save' : 'Add'
            }}</SubmitButton>
            <Button variant="secondary" as-child>
                <Link :href="show(travelRequestId)">Cancel</Link>
            </Button>
        </div>
    </form>
</template>
