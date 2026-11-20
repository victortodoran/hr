<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import TravelRequestController from '@/actions/App/Http/Controllers/Travel/TravelRequestController';
import DateRangePicker from '@/components/DateRangePicker.vue';
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
import { formatPeriod } from '@/lib/dates';
import type { TravelAssignment } from '@/types';

/**
 * Requesting travel in a sheet over the Travel page (rule L14): the
 * Assignment, chosen already when there is only one (V11), the
 * destination, the dates, every day counted, weekends included, within
 * the Assignment's dates, and the purpose. Once requested, the page shows
 * it under Upcoming and the sheet closes.
 */
const props = defineProps<{
    /** The earliest first date, as YYYY-MM-DD. */
    today: string;
    /** The Assignments not ended before today. */
    assignments: TravelAssignment[];
}>();

const open = defineModel<boolean>('open', { required: true });

const FORM_ID = 'request-travel';

const form = useForm({
    assignment_id: '',
    destination: '',
    first_date: '',
    last_date: '',
    purpose: '',
});

/** The only Assignment's ID, or '' when there is a choice to make. */
function onlyAssignment(): string {
    return props.assignments.length === 1
        ? String(props.assignments[0].id)
        : '';
}

/** Starts each request blank, with the only Assignment chosen. */
watch(
    open,
    (isOpen) => {
        if (isOpen) {
            form.defaults('assignment_id', onlyAssignment());
            form.reset();
            form.clearErrors();
        }
    },
    { immediate: true },
);

const chosenAssignment = computed(() =>
    props.assignments.find(
        (assignment) => String(assignment.id) === form.assignment_id,
    ),
);

/** The earliest first date: today, or the Assignment's start if later. */
const earliestDate = computed(() => {
    const start = chosenAssignment.value?.start_date;

    return start !== undefined && start > props.today ? start : props.today;
});

function request(): void {
    form.submit(TravelRequestController.store(), {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <FormSheet
        v-model:open="open"
        title="Request Travel"
        description="Every day counts, weekends included, and all of them must fall within the Assignment's dates."
    >
        <form
            :id="FORM_ID"
            class="flex flex-col gap-5"
            @submit.prevent="request"
        >
            <div class="grid gap-2">
                <Label for="travel-assignment">Assignment</Label>
                <Select
                    name="assignment_id"
                    required
                    :model-value="form.assignment_id"
                    @update:model-value="
                        form.assignment_id = $event ? String($event) : ''
                    "
                >
                    <SelectTrigger
                        id="travel-assignment"
                        class="w-full"
                        :aria-invalid="Boolean(form.errors.assignment_id)"
                    >
                        <SelectValue placeholder="Choose an Assignment" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="assignment in assignments"
                            :key="assignment.id"
                            :value="String(assignment.id)"
                        >
                            {{ assignment.project
                            }}<template v-if="assignment.client">
                                · {{ assignment.client }}</template
                            >
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-if="chosenAssignment"
                    class="text-caption text-muted-foreground"
                >
                    Runs
                    {{
                        formatPeriod(
                            chosenAssignment.start_date,
                            chosenAssignment.end_date,
                        )
                    }}. Travel for the company itself goes on your Internal
                    Assignment.
                </p>
                <InputError :message="form.errors.assignment_id" />
            </div>
            <div class="grid gap-2">
                <Label for="travel-destination">Destination</Label>
                <Input
                    id="travel-destination"
                    v-model="form.destination"
                    placeholder="Berlin"
                    maxlength="255"
                    required
                    :aria-invalid="Boolean(form.errors.destination)"
                />
                <InputError :message="form.errors.destination" />
            </div>
            <div class="grid gap-2">
                <Label for="travel-dates">Dates</Label>
                <DateRangePicker
                    id="travel-dates"
                    v-model:start="form.first_date"
                    v-model:end="form.last_date"
                    :min="earliestDate"
                    :max="chosenAssignment?.end_date ?? undefined"
                    required
                    :invalid="
                        Boolean(form.errors.first_date ?? form.errors.last_date)
                    "
                />
                <InputError :message="form.errors.first_date" />
                <InputError :message="form.errors.last_date" />
            </div>
            <div class="grid gap-2">
                <Label for="travel-purpose">Purpose</Label>
                <Input
                    id="travel-purpose"
                    v-model="form.purpose"
                    placeholder="Relaunch workshop with the client"
                    maxlength="1000"
                    required
                    :aria-invalid="Boolean(form.errors.purpose)"
                />
                <InputError :message="form.errors.purpose" />
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="ghost" @click="open = false"
                >Cancel</Button
            >
            <SubmitButton :form="FORM_ID" :processing="form.processing"
                >Request Travel</SubmitButton
            >
        </template>
    </FormSheet>
</template>
