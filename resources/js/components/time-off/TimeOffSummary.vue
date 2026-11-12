<script setup lang="ts">
import { formatDate } from '@/lib/dates';
import { formatDays } from '@/lib/time-off';
import type { TimeOffDetails } from '@/types';

defineProps<{
    timeOff: TimeOffDetails;
}>();
</script>

<template>
    <p>
        <span class="font-medium"
            >{{ formatDate(timeOff.first_date) }} –
            {{ formatDate(timeOff.last_date) }}</span
        >
        <span class="text-muted-foreground">
            · {{ formatDays(timeOff.days) }} ·
            {{ timeOff.is_paid ? 'Paid' : 'Unpaid' }}</span
        >
        <span
            v-if="timeOff.decided_by !== null"
            class="block text-muted-foreground"
            >{{ timeOff.state === 'declined' ? 'Declined' : 'Approved' }} by
            {{ timeOff.decided_by
            }}<template v-if="timeOff.decline_reason !== null"
                >: “{{ timeOff.decline_reason }}”</template
            ></span
        >
    </p>
</template>
