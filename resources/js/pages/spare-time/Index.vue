<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import OverBookedBadge from '@/components/assignments/OverBookedBadge.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ValueOr from '@/components/ValueOr.vue';
import { formatDate } from '@/lib/dates';
import { index as assignments } from '@/routes/employees/assignments';
import { index } from '@/routes/spare-time';
import type { SpareTimeEntry } from '@/types';

const props = defineProps<{
    day: string;
    employees: SpareTimeEntry[];
    errors?: { day?: string };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Spare time', href: index() }],
});

const chosenDay = ref(props.day);

function lookAt(day: string): void {
    if (day !== '') {
        router.get(index({ query: { day } }), {}, { preserveState: true });
    }
}
</script>

<template>
    <Head title="Spare time" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            title="Spare time"
            description="Who has internal time above 0% on a day, to staff new Projects"
        />

        <div class="grid max-w-xs gap-2">
            <Label for="spare-time-day">Day</Label>
            <Input
                id="spare-time-day"
                v-model="chosenDay"
                type="date"
                @change="lookAt(chosenDay)"
            />
            <InputError :message="errors?.day" />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Employee</th>
                        <th class="px-4 py-2 font-medium">
                            Internal Job Title
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            Internal time
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="employee in employees" :key="employee.id">
                        <td class="px-4 py-2 font-medium">
                            <Link
                                :href="assignments(employee.id)"
                                class="underline-offset-4 hover:underline"
                                >{{ employee.name }}</Link
                            >
                            <OverBookedBadge
                                v-if="employee.is_over_booked"
                                class="ml-2"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <ValueOr :value="employee.job_title" />
                        </td>
                        <td class="px-4 py-2 text-right">
                            {{ employee.internal_time }}%
                        </td>
                    </tr>
                    <tr v-if="employees.length === 0">
                        <td
                            colspan="3"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            Nobody you can see has spare time on
                            {{ formatDate(day) }}.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
