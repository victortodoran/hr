<script setup lang="ts">
import { Form, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PayEntryController from '@/actions/App/Http/Controllers/PayEntryController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { formatDate, formatPeriod, today } from '@/lib/dates';
import { formatPay, payInEffectOn } from '@/lib/pay';
import type { AssignmentRecord, PayEntryRecord } from '@/types';

const props = defineProps<{
    assignment: AssignmentRecord & { pay: PayEntryRecord[] };
    /** The company currency; only given to those who may manage Pay. */
    currency?: string;
}>();

const open = ref(false);
const inEffectOn = ref(today());
const entryInEffect = computed(() =>
    inEffectOn.value
        ? payInEffectOn(props.assignment.pay, inEffectOn.value)
        : null,
);
const canManagePay = computed(() => props.currency !== undefined);
const fieldId = (field: string): string =>
    `pay-${field}-${props.assignment.id}`;

const form = useForm({
    kind: 'fixed',
    amount: '',
    effective_from: '',
});

function add(): void {
    form.submit(PayEntryController.store(props.assignment.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">Pay</Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        assignment.project.is_internal
                            ? 'Internal Pay'
                            : `Pay on ${assignment.project.name}`
                    }}
                </DialogTitle>
                <DialogDescription>
                    Each entry takes effect from its date until the next one.
                    The Assignment runs
                    {{
                        formatPeriod(
                            assignment.start_date,
                            assignment.end_date,
                        )
                    }}.
                </DialogDescription>
            </DialogHeader>

            <div v-if="assignment.pay.length > 0" class="grid gap-2">
                <Label :for="fieldId('in-effect-on')">In effect on</Label>
                <Input
                    :id="fieldId('in-effect-on')"
                    v-model="inEffectOn"
                    type="date"
                    class="w-48"
                />
                <p
                    v-if="inEffectOn && entryInEffect === null"
                    class="text-sm text-muted-foreground"
                >
                    No Pay yet on {{ formatDate(inEffectOn) }}.
                </p>
            </div>

            <ul
                v-if="assignment.pay.length > 0"
                class="divide-y rounded-lg border"
            >
                <li
                    v-for="entry in assignment.pay"
                    :key="entry.id"
                    class="flex items-center justify-between gap-4 px-4 py-2 text-sm"
                >
                    <div>
                        <p class="font-medium">{{ formatPay(entry) }}</p>
                        <p class="text-muted-foreground">
                            from {{ formatDate(entry.effective_from) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge v-if="entry.id === entryInEffect?.id"
                            >In effect</Badge
                        >
                        <Form
                            v-if="canManagePay"
                            v-bind="
                                PayEntryController.destroy.form([
                                    assignment.id,
                                    entry.id,
                                ])
                            "
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                :disabled="processing"
                                >Remove</Button
                            >
                        </Form>
                    </div>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">No Pay yet.</p>

            <form v-if="canManagePay" class="space-y-4" @submit.prevent="add">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="fieldId('kind')">Kind</Label>
                        <Select v-model="form.kind">
                            <SelectTrigger :id="fieldId('kind')" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="fixed"
                                    >Fixed Pay, per month</SelectItem
                                >
                                <SelectItem value="hourly"
                                    >Hourly Pay, per hour</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.kind" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="fieldId('amount')"
                            >Amount ({{ currency }})</Label
                        >
                        <Input
                            :id="fieldId('amount')"
                            v-model="form.amount"
                            inputmode="decimal"
                            placeholder="e.g. 4500.00"
                            required
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label :for="fieldId('effective-from')"
                        >Takes effect from</Label
                    >
                    <Input
                        :id="fieldId('effective-from')"
                        v-model="form.effective_from"
                        type="date"
                        :min="assignment.start_date"
                        :max="assignment.end_date ?? undefined"
                        required
                    />
                    <InputError :message="form.errors.effective_from" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button">Close</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing"
                        >Add Pay</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
