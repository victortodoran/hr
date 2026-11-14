<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import LockController from '@/actions/App/Http/Controllers/LockController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatHours } from '@/lib/work-entries';
import type { LockSummary, WorkEntryListFilters } from '@/types';

const props = defineProps<{
    /** The selected Work Entries. */
    ids: number[];
    /**
     * The filters they were selected under, for the Pending warning: the
     * same month, Employee and Project.
     */
    filters: WorkEntryListFilters;
}>();

const emit = defineEmits<{
    locked: [];
}>();

const open = ref(false);
const summary = ref<LockSummary | null>(null);
const failed = ref(false);
const http = useHttp();

async function loadSummary(isOpen: boolean): Promise<void> {
    if (!isOpen) {
        return;
    }

    summary.value = null;
    failed.value = false;

    try {
        summary.value = (await http.submit(
            LockController.create({
                query: {
                    ids: props.ids,
                    month: props.filters.month,
                    ...(typeof props.filters.employee === 'number'
                        ? { employee: props.filters.employee }
                        : {}),
                    ...(props.filters.project
                        ? { project: props.filters.project }
                        : {}),
                },
            }),
        )) as LockSummary;
    } catch {
        failed.value = true;
    }
}

function lock(): void {
    router.post(
        LockController.store.url(),
        { ids: props.ids },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
                emit('locked');
            },
        },
    );
}
</script>

<template>
    <Dialog
        v-model:open="open"
        @update:open="(isOpen: boolean) => loadSummary(isOpen)"
    >
        <DialogTrigger as-child>
            <Button variant="outline" :disabled="ids.length === 0">Lock</Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Lock Work Entries</DialogTitle>
                <DialogDescription>
                    Locked Work Entries can never be changed, deleted or
                    reopened. This can't be undone.
                </DialogDescription>
            </DialogHeader>

            <p v-if="failed" class="text-sm text-destructive">
                The summary couldn't be loaded. Close this and try again.
            </p>
            <p v-else-if="!summary" class="text-sm text-muted-foreground">
                Adding up the selected Work Entries…
            </p>
            <div v-else class="space-y-4 text-sm">
                <p v-if="summary.locking.count === 0">
                    None of the selected Work Entries is Approved, so there is
                    nothing to lock.
                </p>
                <template v-else>
                    <p>
                        {{ summary.locking.count }} Approved Work
                        {{ summary.locking.count === 1 ? 'Entry' : 'Entries' }}
                        will be locked, {{ formatHours(summary.locking.hours) }}
                        in all.
                    </p>
                    <table class="w-full">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="py-1 font-medium">Project</th>
                                <th class="py-1 font-medium">Employee</th>
                                <th class="py-1 text-right font-medium">
                                    Hours
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in summary.breakdown"
                                :key="`${row.project}-${row.employee}`"
                            >
                                <td class="py-1">{{ row.project }}</td>
                                <td class="py-1">{{ row.employee }}</td>
                                <td class="py-1 text-right tabular-nums">
                                    {{ formatHours(row.hours) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <p
                    v-if="summary.pending.count > 0"
                    class="rounded-md border border-amber-500/50 bg-amber-500/10 p-3"
                >
                    {{ summary.pending.count }} Pending Work
                    {{ summary.pending.count === 1 ? 'Entry' : 'Entries' }}
                    ({{ formatHours(summary.pending.hours) }}) match the same
                    filters and won't be locked. You may want to wait until
                    they're approved.
                </p>
            </div>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="!summary || summary.locking.count === 0"
                    @click="lock"
                    >Lock for good</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
