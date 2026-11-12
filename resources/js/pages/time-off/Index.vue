<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TimeOffCancellationController from '@/actions/App/Http/Controllers/TimeOffCancellationController';
import TimeOffController from '@/actions/App/Http/Controllers/TimeOffController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TimeOffSummary from '@/components/time-off/TimeOffSummary.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/time-off';
import type { TimeOffRecord } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Time Off', href: index() }],
    },
});

defineProps<{
    year: number;
    today: string;
    /** Paid Days minus Pending and Approved Paid Time Off this year. */
    paidDaysLeft: number;
    timeOff: TimeOffRecord[];
}>();
</script>

<template>
    <Head title="Time Off" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            title="Time Off"
            description="Request days away from work, Paid or Unpaid, and cancel them before they start"
        />

        <p class="text-sm">
            <span
                class="text-2xl font-semibold tabular-nums"
                :class="{ 'text-destructive': paidDaysLeft < 0 }"
                >{{ paidDaysLeft }}</span
            >
            <span class="ml-2 text-muted-foreground"
                >Paid Days left in {{ year }}</span
            >
        </p>

        <section class="space-y-3 rounded-lg border p-4">
            <h2 class="font-medium">Request Time Off</h2>
            <Form
                v-bind="TimeOffController.store.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="time-off-first-date">First date</Label>
                        <Input
                            id="time-off-first-date"
                            name="first_date"
                            type="date"
                            :min="today"
                            required
                        />
                        <InputError :message="errors.first_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="time-off-last-date">Last date</Label>
                        <Input
                            id="time-off-last-date"
                            name="last_date"
                            type="date"
                            :min="today"
                            required
                        />
                        <InputError :message="errors.last_date" />
                    </div>
                </div>
                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Kind</legend>
                    <div class="flex gap-6 text-sm">
                        <label class="flex items-center gap-2">
                            <input
                                type="radio"
                                name="is_paid"
                                value="1"
                                checked
                            />
                            Paid
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="is_paid" value="0" />
                            Unpaid
                        </label>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Only weekdays count. Paid Time Off uses your Paid Days
                        for the year each day falls in.
                    </p>
                    <InputError :message="errors.is_paid" />
                </fieldset>
                <SubmitButton :processing="processing"
                    >Request Time Off</SubmitButton
                >
            </Form>
        </section>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Your Time Off</h2>
            <p
                v-if="timeOff.length === 0"
                class="text-sm text-muted-foreground"
            >
                You haven't requested any Time Off yet.
            </p>
            <ul v-else class="divide-y rounded-lg border text-sm">
                <li
                    v-for="item in timeOff"
                    :key="item.id"
                    class="flex flex-wrap items-center justify-between gap-4 px-4 py-3"
                >
                    <TimeOffSummary :time-off="item" />
                    <div class="flex items-center gap-2">
                        <StatusBadge :state="item.state" />
                        <Form
                            v-if="item.can_cancel"
                            v-bind="
                                TimeOffCancellationController.store.form(
                                    item.id,
                                )
                            "
                            :options="{ preserveScroll: true }"
                            v-slot="{ processing }"
                        >
                            <SubmitButton
                                :processing="processing"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                >Cancel</SubmitButton
                            >
                        </Form>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
