<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import TravelExpenseController from '@/actions/App/Http/Controllers/Travel/TravelExpenseController';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import type {
    TravelCurrency,
    TravelExpenseCategory,
    TravelExpenseRecord,
} from '@/types';

/**
 * Records a Travel Expense on a Travel Request, or, given one, changes it.
 * A new one always needs its receipt; a change keeps the receipt unless a
 * new file is chosen.
 */
const props = defineProps<{
    today: string;
    currency: TravelCurrency;
    /** The Travel Request a new Travel Expense is recorded on. */
    travelRequestId: number;
    /** The Travel Expense to change; none to record a new one. */
    expense?: TravelExpenseRecord;
}>();

const open = ref(false);

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

const isChange = computed(() => props.expense !== undefined);
const idPrefix = computed(() =>
    props.expense
        ? `travel-expense-${props.expense.id}`
        : `travel-expense-new-${props.travelRequestId}`,
);
const amountStep = computed(() =>
    props.currency.decimals === 0
        ? '1'
        : `0.${'0'.repeat(props.currency.decimals - 1)}1`,
);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.defaults({
        paid_on: props.expense?.paid_on ?? '',
        category: props.expense?.category ?? '',
        amount: props.expense?.amount_typed ?? '',
        description: props.expense?.description ?? '',
        receipt: null,
    });
    form.reset();
    form.clearErrors();
});

function chooseReceipt(event: Event): void {
    form.receipt = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function save(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.expense) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            TravelExpenseController.update.url(props.expense.id),
            options,
        );

        return;
    }

    form.transform((data) => data).submit(
        TravelExpenseController.store(props.travelRequestId),
        options,
    );
}

function fieldId(field: string): string {
    return `${idPrefix.value}-${field}`;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <slot name="trigger">
                <Button variant="outline" size="sm">Add expense</Button>
            </slot>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{
                    isChange ? 'Change expense' : 'Add expense'
                }}</DialogTitle>
                <DialogDescription>
                    Money you paid yourself for this travel, in
                    {{ currency.code }}. Note an amount paid in another currency
                    in the description.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="fieldId('paid-on')">Date paid</Label>
                        <Input
                            :id="fieldId('paid-on')"
                            v-model="form.paid_on"
                            type="date"
                            :max="today"
                            required
                        />
                        <InputError :message="form.errors.paid_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="fieldId('amount')"
                            >Amount ({{ currency.code }})</Label
                        >
                        <Input
                            :id="fieldId('amount')"
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
                    <Label :for="fieldId('category')">Category</Label>
                    <Select
                        :model-value="form.category"
                        @update:model-value="
                            form.category = $event
                                ? ($event as TravelExpenseCategory)
                                : ''
                        "
                    >
                        <SelectTrigger :id="fieldId('category')" class="w-full">
                            <SelectValue placeholder="Choose a category" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(
                                    label, category
                                ) in travelExpenseCategories"
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
                    <Label :for="fieldId('description')">Description</Label>
                    <Input
                        :id="fieldId('description')"
                        v-model="form.description"
                        placeholder="Hotel, three nights"
                        maxlength="1000"
                        required
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="grid gap-2">
                    <Label :for="fieldId('receipt')">Receipt</Label>
                    <input
                        :id="fieldId('receipt')"
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                        :required="!isChange"
                        class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                        @change="chooseReceipt"
                    />
                    <p class="text-xs text-muted-foreground">
                        A JPG, PNG or PDF of up to 10 MB.<template
                            v-if="isChange"
                        >
                            Choose a file only to replace the receipt.</template
                        >
                    </p>
                    <InputError :message="form.errors.receipt" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <SubmitButton :processing="form.processing">{{
                        isChange ? 'Save' : 'Add'
                    }}</SubmitButton>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
