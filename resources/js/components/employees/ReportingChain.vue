<script setup lang="ts">
import { computed } from 'vue';
import ChainMemberCard from '@/components/employees/ChainMemberCard.vue';
import type { ChainMember, ReportingChainView } from '@/types';

const props = defineProps<{
    chain: ReportingChainView;
}>();

const line = computed<ChainMember[]>(() => [
    ...props.chain.above,
    props.chain.employee,
]);
</script>

<template>
    <div class="flex max-w-sm min-w-0 flex-col" aria-label="Reporting Chain">
        <ol class="flex flex-col">
            <li
                v-for="(member, position) in line"
                :key="member.id"
                class="flex flex-col"
            >
                <div
                    v-if="position > 0"
                    class="ml-5.5 h-2 w-px bg-border-strong"
                    aria-hidden="true"
                />
                <ChainMemberCard
                    :member="member"
                    :is-viewed="member.id === chain.employee.id"
                />
            </li>
        </ol>

        <ul
            v-if="chain.below.length > 0"
            class="mt-2 ml-5.5 flex flex-col gap-0.5 border-l border-border-strong pl-3"
            aria-label="Employees they are the Manager of"
        >
            <li v-for="member in chain.below" :key="member.id">
                <ChainMemberCard :member="member" />
            </li>
        </ul>
    </div>
</template>
