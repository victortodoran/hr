<script setup lang="ts" generic="Row">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    rows: Row[];
    rowKey: (row: Row) => string;
}>();

defineSlots<{
    default: (props: { row: Row }) => unknown;
}>();

const PHONE_ROWS = 3;
const ROWS = 5;

const showsAll = ref(false);

function hiddenClass(position: number): string {
    if (showsAll.value) {
        return '';
    }

    if (position >= ROWS) {
        return 'hidden';
    }

    return position >= PHONE_ROWS ? 'max-sm:hidden' : '';
}

const viewAllClass = computed(() =>
    props.rows.length > ROWS ? '' : 'sm:hidden',
);
</script>

<template>
    <div class="flex flex-col">
        <ul class="divide-y" data-test="widget-rows">
            <li
                v-for="(row, position) in rows"
                :key="rowKey(row)"
                :class="hiddenClass(position)"
            >
                <slot :row="row" />
            </li>
        </ul>
        <Button
            v-if="!showsAll && rows.length > PHONE_ROWS"
            type="button"
            variant="ghost"
            size="sm"
            class="self-start text-muted-foreground max-sm:h-11"
            :class="viewAllClass"
            @click="showsAll = true"
            >View all</Button
        >
    </div>
</template>
