<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TimeOffCancellationController from '@/actions/App/Http/Controllers/TimeOffCancellationController';
import TimeOffController from '@/actions/App/Http/Controllers/TimeOffController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/dates';
import { index } from '@/routes/time-off';
import type { TimeOffRecord, TimeOffState } from '@/types';

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

const stateNames: Record<TimeOffState, string> = {
    pending: 'Pending',
    approved: 'Approved',
    declined: 'Declined',
    cancelled: 'Cancelled',
};

/**
 * Days as people read them, such as "1 day" or "3 days".
 */
function formatDays(days: number): string {
    return days === 1 ? '1 day' : `${days} days`;
}
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
                <Button type="submit" :disabled="processing"
                    >Request Time Off</Button
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
                    <p>
                        <span class="font-medium"
                            >{{ formatDate(item.first_date) }} –
                            {{ formatDate(item.last_date) }}</span
                        >
                        <span class="text-muted-foreground">
                            · {{ formatDays(item.days) }} ·
                            {{ item.is_paid ? 'Paid' : 'Unpaid' }}</span
                        >
                    </p>
                    <div class="flex items-center gap-2">
                        <Badge variant="outline">{{
                            stateNames[item.state]
                        }}</Badge>
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
                            <Button
                                type="submit"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                :disabled="processing"
                                >Cancel</Button
                            >
                        </Form>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
