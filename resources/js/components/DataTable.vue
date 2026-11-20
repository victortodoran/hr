<script setup lang="ts" generic="Row extends object">
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import type { TableColumn } from '@/types';

const props = defineProps<{
    columns: TableColumn[];
    rows: Row[];
    groupBy?: (row: Row) => string;
    /** Every group in order, so a group with no rows still shows its group row. */
    groups?: string[];
    selection?: {
        isSelected: (id: number) => boolean;
        toggle: (id: number, checked: boolean) => void;
    };
    rowLabel?: (row: Row) => string;
    groupLabel?: (group: string) => string;
    onRowClick?: (row: Row) => void;
    hasAfterRow?: (row: Row) => boolean;
    rowClass?: (row: Row) => string;
}>();

defineSlots<
    {
        empty?: () => unknown;
        group?: (props: { group: string; rows: Row[] }) => unknown;
        lead?: () => unknown;
        'after-row'?: (props: { row: Row }) => unknown;
    } & {
        [slot: `cell-${string}`]: (props: { row: Row }) => unknown;
    } & {
        [slot: `head-${string}`]: () => unknown;
    }
>();

type Line =
    | { kind: 'group'; group: string; rows: Row[] }
    | { kind: 'row'; row: Row; index: number };

const isPhone = useMediaQuery('(max-width: 639px)');

const stacks = computed(
    () => isPhone.value && props.columns.some((column) => column.phone),
);

const phoneColumns = computed(() => ({
    primary: props.columns.filter((column) => column.phone === 'primary'),
    secondary: props.columns.filter((column) => column.phone === 'secondary'),
    end: props.columns.filter((column) => column.phone === 'end'),
}));

const lines = computed<Line[]>(() => {
    const groupBy = props.groupBy;

    if (props.groups && groupBy) {
        return props.groups.flatMap((group): Line[] => {
            const rows = props.rows
                .map((row, index) => ({ row, index }))
                .filter(({ row }) => groupBy(row) === group);

            return [
                { kind: 'group', group, rows: rows.map(({ row }) => row) },
                ...rows.map(({ row, index }) => ({
                    kind: 'row' as const,
                    row,
                    index,
                })),
            ];
        });
    }

    const result: Line[] = [];
    let current: Extract<Line, { kind: 'group' }> | null = null;

    props.rows.forEach((row, index) => {
        const group = props.groupBy?.(row);

        if (group !== undefined && current?.group !== group) {
            current = { kind: 'group', group, rows: [] };
            result.push(current);
        }

        current?.rows.push(row);
        result.push({ kind: 'row', row, index });
    });

    return result;
});

const columnCount = computed(
    () => props.columns.length + (props.selection ? 1 : 0),
);

function keyOf(row: Row, index: number): PropertyKey {
    return 'id' in row ? (row.id as PropertyKey) : index;
}

function idOf(row: Row): number {
    return (row as { id: number }).id;
}

function fieldOf(row: Row, key: string): unknown {
    return (row as Record<string, unknown>)[key];
}

function isRowSelected(row: Row): boolean {
    return props.selection?.isSelected(idOf(row)) ?? false;
}

function stateOf(rows: Row[]): boolean | 'indeterminate' {
    const ticked = rows.filter(isRowSelected).length;

    if (ticked === 0) {
        return false;
    }

    return ticked === rows.length ? true : 'indeterminate';
}

function tickAll(rows: Row[], checked: boolean): void {
    rows.forEach((row) => props.selection?.toggle(idOf(row), checked));
}

function clicked(row: Row, event: MouseEvent): void {
    const target = event.target as HTMLElement;

    if (
        props.onRowClick &&
        !target.closest('a, button, input, label, [role="checkbox"]')
    ) {
        props.onRowClick(row);
    }
}

function hasAfter(row: Row): boolean {
    return props.hasAfterRow?.(row) ?? false;
}

function cellClass(column: TableColumn): string[] {
    return [
        'px-3 py-2 sm:px-4',
        column.minor ? 'hidden sm:table-cell' : '',
        column.align === 'end' ? 'text-right' : 'text-left',
    ];
}
</script>

<template>
    <div
        v-if="stacks"
        data-slot="data-table"
        class="overflow-hidden rounded-lg border bg-background text-body"
    >
        <slot name="lead" />
        <template
            v-for="line in lines"
            :key="
                line.kind === 'group'
                    ? `group-${line.group}`
                    : keyOf(line.row, line.index)
            "
        >
            <div
                v-if="line.kind === 'group'"
                data-slot="group-row"
                class="flex items-center gap-3 border-b bg-surface px-4 py-2 text-meta"
            >
                <Checkbox
                    v-if="selection"
                    :model-value="stateOf(line.rows)"
                    :aria-label="`Select ${groupLabel?.(line.group) ?? 'this group'}`"
                    @update:model-value="tickAll(line.rows, $event === true)"
                />
                <div class="min-w-0 flex-1">
                    <slot name="group" :group="line.group" :rows="line.rows" />
                </div>
            </div>
            <div
                v-else
                data-slot="data-row"
                class="flex items-start gap-3 border-b px-4 py-3 last:border-b-0"
                :class="[
                    isRowSelected(line.row) ? 'bg-primary-soft' : '',
                    onRowClick ? 'cursor-pointer' : '',
                    hasAfter(line.row) ? 'border-b-0' : '',
                    rowClass?.(line.row) ?? '',
                ]"
                @click="clicked(line.row, $event)"
            >
                <Checkbox
                    v-if="selection"
                    class="mt-0.5"
                    :model-value="isRowSelected(line.row)"
                    :aria-label="`Select ${rowLabel?.(line.row) ?? 'row'}`"
                    @update:model-value="
                        selection.toggle(idOf(line.row), $event === true)
                    "
                />
                <div class="min-w-0 flex-1">
                    <div
                        v-for="column in phoneColumns.primary"
                        :key="column.key"
                        class="font-medium"
                    >
                        <slot :name="`cell-${column.key}`" :row="line.row">{{
                            fieldOf(line.row, column.key)
                        }}</slot>
                    </div>
                    <div
                        v-if="phoneColumns.secondary.length > 0"
                        class="mt-0.5 text-meta text-muted-foreground"
                    >
                        <template
                            v-for="(column, position) in phoneColumns.secondary"
                            :key="column.key"
                            ><template v-if="position > 0"> · </template
                            ><slot
                                :name="`cell-${column.key}`"
                                :row="line.row"
                                >{{ fieldOf(line.row, column.key) }}</slot
                            ></template
                        >
                    </div>
                </div>
                <div
                    v-if="phoneColumns.end.length > 0"
                    class="shrink-0 text-right"
                >
                    <div v-for="column in phoneColumns.end" :key="column.key">
                        <slot :name="`cell-${column.key}`" :row="line.row">{{
                            fieldOf(line.row, column.key)
                        }}</slot>
                    </div>
                </div>
            </div>
            <div
                v-if="line.kind === 'row' && hasAfter(line.row)"
                data-slot="after-row"
                class="border-b px-4 pb-3 last:border-b-0"
            >
                <slot name="after-row" :row="line.row" />
            </div>
        </template>
        <slot v-if="lines.length === 0" name="empty" />
    </div>

    <div
        v-else
        data-slot="data-table"
        class="overflow-x-auto rounded-lg border"
    >
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-muted-foreground">
                <tr>
                    <th
                        v-if="selection"
                        scope="col"
                        class="w-11 px-3 py-2 sm:px-4"
                    >
                        <Checkbox
                            v-if="rows.length > 0"
                            :model-value="stateOf(rows)"
                            aria-label="Select every row shown"
                            @update:model-value="tickAll(rows, $event === true)"
                        />
                    </th>
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
                <tr v-if="$slots.lead && rows.length > 0" data-slot="lead-row">
                    <td :colspan="columnCount" class="p-0">
                        <slot name="lead" />
                    </td>
                </tr>
                <template
                    v-for="line in lines"
                    :key="
                        line.kind === 'group'
                            ? `group-${line.group}`
                            : keyOf(line.row, line.index)
                    "
                >
                    <tr
                        v-if="line.kind === 'group'"
                        data-slot="group-row"
                        class="bg-surface"
                    >
                        <td v-if="selection" class="px-3 py-2 sm:px-4">
                            <Checkbox
                                :model-value="stateOf(line.rows)"
                                :aria-label="`Select ${groupLabel?.(line.group) ?? 'this group'}`"
                                @update:model-value="
                                    tickAll(line.rows, $event === true)
                                "
                            />
                        </td>
                        <td
                            :colspan="columns.length"
                            class="px-3 py-2 text-meta sm:px-4"
                        >
                            <slot
                                name="group"
                                :group="line.group"
                                :rows="line.rows"
                            />
                        </td>
                    </tr>
                    <tr
                        v-else
                        data-slot="data-row"
                        class="group/row"
                        :class="[
                            isRowSelected(line.row)
                                ? 'bg-primary-soft'
                                : onRowClick
                                  ? 'hover:bg-surface'
                                  : '',
                            onRowClick ? 'cursor-pointer' : '',
                            hasAfter(line.row) ? 'border-b-0' : '',
                            rowClass?.(line.row) ?? '',
                        ]"
                        @click="clicked(line.row, $event)"
                    >
                        <td
                            v-if="selection"
                            class="px-3 py-2 align-top sm:px-4"
                        >
                            <Checkbox
                                :model-value="isRowSelected(line.row)"
                                :aria-label="`Select ${rowLabel?.(line.row) ?? 'row'}`"
                                @update:model-value="
                                    selection.toggle(
                                        idOf(line.row),
                                        $event === true,
                                    )
                                "
                            />
                        </td>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="align-top"
                            :class="cellClass(column)"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :row="line.row"
                                >{{ fieldOf(line.row, column.key) }}</slot
                            >
                        </td>
                    </tr>
                    <tr
                        v-if="line.kind === 'row' && hasAfter(line.row)"
                        data-slot="after-row"
                    >
                        <td
                            :colspan="columnCount"
                            class="px-3 pt-0 pb-3 sm:px-4"
                        >
                            <slot name="after-row" :row="line.row" />
                        </td>
                    </tr>
                </template>
                <tr v-if="lines.length === 0">
                    <td :colspan="columnCount">
                        <slot name="empty" />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
