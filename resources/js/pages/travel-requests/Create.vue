<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import TravelRequestController from '@/actions/App/Http/Controllers/Travel/TravelRequestController';
import PageHeader from '@/components/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import PageContainer from '@/components/PageContainer.vue';
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
import { formatDays } from '@/lib/time-off';
import { create, index } from '@/routes/travel-requests';
import type { TravelAssignment } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Travel', href: index() },
            { title: 'Request travel', href: create() },
        ],
    },
});

const props = defineProps<{
    today: string;
    /** The Assignments not ended before today. */
    assignments: TravelAssignment[];
}>();

const MILLISECONDS_IN_A_DAY = 24 * 60 * 60 * 1000;

const form = useForm({
    assignment_id: '',
    destination: '',
    first_date: '',
    last_date: '',
    purpose: '',
});

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

/** Every day from the first to the last date, weekends included. */
const days = computed<number | null>(() => {
    if (form.first_date === '' || form.last_date === '') {
        return null;
    }

    const counted =
        Math.round(
            (Date.parse(form.last_date) - Date.parse(form.first_date)) /
                MILLISECONDS_IN_A_DAY,
        ) + 1;

    return counted > 0 ? counted : null;
});

function request(): void {
    form.submit(TravelRequestController.store());
}
</script>

<template>
    <Head title="Request travel" />

    <PageContainer width="form">
        <PageHeader
            title="Request travel"
            description="Every day counts, weekends included, and all of them must fall within the Assignment's dates. Travel for the company itself goes on your Internal Assignment."
        />

        <form class="space-y-6" @submit.prevent="request">
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
                    <SelectTrigger id="travel-assignment" class="w-full">
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
                    class="text-xs text-muted-foreground"
                >
                    Runs
                    {{
                        formatPeriod(
                            chosenAssignment.start_date,
                            chosenAssignment.end_date,
                        )
                    }}
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
                />
                <InputError :message="form.errors.destination" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="travel-first-date">First date</Label>
                    <Input
                        id="travel-first-date"
                        v-model="form.first_date"
                        type="date"
                        :min="earliestDate"
                        :max="chosenAssignment?.end_date ?? undefined"
                        required
                    />
                    <InputError :message="form.errors.first_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="travel-last-date">Last date</Label>
                    <Input
                        id="travel-last-date"
                        v-model="form.last_date"
                        type="date"
                        :min="form.first_date || earliestDate"
                        :max="chosenAssignment?.end_date ?? undefined"
                        required
                    />
                    <InputError :message="form.errors.last_date" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="travel-purpose">Purpose</Label>
                <Input
                    id="travel-purpose"
                    v-model="form.purpose"
                    placeholder="Relaunch workshop with the client"
                    maxlength="1000"
                    required
                />
                <InputError :message="form.errors.purpose" />
            </div>

            <p
                v-if="days !== null"
                class="rounded-md bg-muted px-3 py-2 text-sm font-medium"
                aria-live="polite"
            >
                {{ formatDays(days) }}
            </p>

            <div class="flex gap-2">
                <SubmitButton :processing="form.processing"
                    >Request</SubmitButton
                >
                <Button variant="secondary" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </form>
    </PageContainer>
</template>
