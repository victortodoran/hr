<script setup lang="ts">
import { UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmploymentStatusBadge from '@/components/employees/EmploymentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { countOf } from '@/lib/roles';
import type { RoleCandidate } from '@/types';

const props = withDefaults(
    defineProps<{
        roleName: string;
        candidates: RoleCandidate[];
        processing?: boolean;
    }>(),
    { processing: false },
);

const emit = defineEmits<{
    add: [ids: number[]];
}>();

const open = ref(false);
const search = ref('');
const chosen = ref<number[]>([]);

const shown = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return props.candidates.filter((candidate) =>
        candidate.name.toLocaleLowerCase().includes(query),
    );
});

function opened(isOpen: boolean): void {
    if (isOpen) {
        search.value = '';
        chosen.value = [];
    }
}

function toggle(id: number, checked: boolean): void {
    chosen.value = checked
        ? [...chosen.value, id]
        : chosen.value.filter((chosenId) => chosenId !== id);
}

const addLabel = computed(() =>
    chosen.value.length === 0
        ? 'Add people'
        : `Add ${countOf(chosen.value.length, 'person', 'people')}`,
);

function add(): void {
    emit('add', chosen.value);
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open" @update:open="opened">
        <PopoverTrigger as-child>
            <Button variant="secondary" class="max-sm:h-11">
                <UserPlus aria-hidden="true" />
                Add people
            </Button>
        </PopoverTrigger>
        <PopoverContent align="end" class="grid w-80 gap-3 p-3">
            <Input
                v-model="search"
                type="search"
                placeholder="Search Employees"
                :aria-label="`Search Employees to add to ${roleName}`"
                class="h-11"
            />
            <ul
                v-if="shown.length > 0"
                class="grid max-h-64 gap-1 overflow-y-auto"
                :aria-label="`Employees who don't hold ${roleName}`"
            >
                <li v-for="candidate in shown" :key="candidate.id">
                    <Label
                        class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-2 font-normal hover:bg-muted/60"
                    >
                        <Checkbox
                            :model-value="chosen.includes(candidate.id)"
                            @update:model-value="
                                toggle(candidate.id, $event === true)
                            "
                        />
                        <span class="min-w-0 flex-1 truncate">{{
                            candidate.name
                        }}</span>
                        <EmploymentStatusBadge
                            v-if="candidate.status !== 'current'"
                            :status="candidate.status"
                        />
                    </Label>
                </li>
            </ul>
            <p v-else class="px-2 py-3 text-sm text-muted-foreground">
                {{
                    candidates.length === 0
                        ? `Everyone who can hold ${roleName} already does.`
                        : 'Nobody matches that search.'
                }}
            </p>
            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    variant="secondary"
                    class="max-sm:h-11"
                    @click="open = false"
                    >Cancel</Button
                >
                <Button
                    class="max-sm:h-11"
                    :disabled="chosen.length === 0 || processing"
                    @click="add"
                    >{{ addLabel }}</Button
                >
            </div>
        </PopoverContent>
    </Popover>
</template>
