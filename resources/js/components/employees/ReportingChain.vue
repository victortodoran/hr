<script setup lang="ts">
import ChainMemberCard from '@/components/employees/ChainMemberCard.vue';
import type { ReportingChainView } from '@/types';

defineProps<{
    chain: ReportingChainView;
}>();
</script>

<template>
    <div class="flex flex-col items-center" aria-label="Reporting Chain">
        <ol class="flex w-full flex-col items-center">
            <li
                v-for="member in chain.above"
                :key="member.id"
                class="flex w-full flex-col items-center"
            >
                <ChainMemberCard :member="member" />
                <div class="h-6 w-px bg-border" aria-hidden="true" />
            </li>
            <li class="flex w-full flex-col items-center">
                <ChainMemberCard :member="chain.employee" is-viewed />
                <div
                    v-if="chain.below.length > 0"
                    class="h-6 w-px bg-border"
                    aria-hidden="true"
                />
            </li>
        </ol>

        <ul
            v-if="chain.below.length > 0"
            class="grid w-full justify-items-center gap-2 border-t pt-4 sm:grid-cols-2"
            aria-label="Employees they are the Manager of"
        >
            <li v-for="member in chain.below" :key="member.id" class="w-full">
                <ChainMemberCard :member="member" class="mx-auto" />
            </li>
        </ul>
    </div>
</template>
