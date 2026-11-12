<script setup lang="ts" generic="Row extends object">
import type { TableColumn } from '@/types';

/**
 * The table every list uses. Each cell shows the row's field named by the
 * column's key, unless the page fills the `cell-<key>` slot; each header shows
 * the column's label, unless the page fills the `head-<key>` slot. When there
 * are no rows, the `empty` slot (usually an EmptyState) fills the table body.
 */
const props = defineProps<{
    columns: TableColumn[];
    rows: Row[];
    rowKey?: (row: Row) => PropertyKey;
}>();

defineSlots<
    {
        empty?: () => unknown;
    } & {
        [slot: `cell-${string}`]: (props: { row: Row }) => unknown;
    } & {
        [slot: `head-${string}`]: () => unknown;
    }
>();

function keyOf(row: Row, index: number): PropertyKey {
    if (props.rowKey) {
        return props.rowKey(row);
    }

    return 'id' in row ? (row.id as PropertyKey) : index;
}

function fieldOf(row: Row, key: string): unknown {
    return (row as Record<string, unknown>)[key];
}

function cellClass(column: TableColumn): string[] {
    return [
        'px-4 py-2',
        column.minor ? 'hidden sm:table-cell' : '',
        column.align === 'end' ? 'text-right' : 'text-left',
    ];
}
</script>

<template>
    <div class="overflow-x-auto rounded-lg border">
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-muted-foreground">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="font-medium"
                        :class="cellClass(column)"
                    >
                        <slot :name="`head-${column.key}`">{{
                            column.label
                        }}</slot>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr v-for="(row, index) in rows" :key="keyOf(row, index)">
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="align-top"
                        :class="cellClass(column)"
                    >
                        <slot :name="`cell-${column.key}`" :row="row">{{
                            fieldOf(row, column.key)
                        }}</slot>
                    </td>
                </tr>
                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length">
                        <slot name="empty" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
