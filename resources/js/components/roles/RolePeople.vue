<script setup lang="ts">
import { Form, useForm } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import RoleHolderController from '@/actions/App/Http/Controllers/Access/RoleHolderController';
import InputError from '@/components/InputError.vue';
import AddPeoplePopover from '@/components/roles/AddPeoplePopover.vue';
import RoleHolderList from '@/components/roles/RoleHolderList.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import type { RoleCandidate, RoleRecord } from '@/types';

/**
 * The people holding a Role, below its editor: searchable, each linked to
 * their page, with Remove, and "Add people" above them. Adding and
 * removing save at once, keeping any unsaved changes to the Role above.
 */
const props = defineProps<{
    role: RoleRecord;
    /** Every Employee who can be given a Role, holding this one or not. */
    employees: RoleCandidate[];
}>();

const search = ref('');

const holders = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return props.role.holders.filter((holder) =>
        holder.name.toLocaleLowerCase().includes(query),
    );
});

const candidates = computed(() =>
    props.employees.filter(
        (employee) =>
            !props.role.holders.some((holder) => holder.id === employee.id),
    ),
);

const add = useForm<{ employee_ids: number[] }>({ employee_ids: [] });

function addPeople(ids: number[]): void {
    add.employee_ids = ids;
    add.submit(RoleHolderController.store(props.role.id), {
        errorBag: `give-${props.role.id}`,
        preserveScroll: true,
        preserveState: true,
    });
}

const addError = computed(
    () =>
        Object.values(add.errors as Record<string, string>).find(Boolean) ??
        undefined,
);
</script>

<template>
    <section aria-labelledby="role-people" class="flex flex-col gap-3">
        <header class="flex items-center justify-between gap-3">
            <h3 id="role-people" class="text-section">People</h3>
            <AddPeoplePopover
                :role-name="role.name"
                :candidates="candidates"
                :processing="add.processing"
                @add="addPeople"
            />
        </header>
        <InputError :message="addError" />
        <div v-if="role.holders.length > 5" class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="search"
                type="search"
                placeholder="Search people"
                :aria-label="`Search people holding ${role.name}`"
                class="pl-9 max-sm:h-11"
            />
        </div>
        <RoleHolderList
            :holders="holders"
            linked
            :empty="
                role.holders.length === 0
                    ? 'Nobody holds this Role yet.'
                    : `Nobody holding it matches “${search}”.`
            "
        >
            <template #actions="{ holder }">
                <Form
                    v-bind="
                        RoleHolderController.destroy.form([role.id, holder.id])
                    "
                    :error-bag="`take-${role.id}-${holder.id}`"
                    :options="{ preserveScroll: true, preserveState: true }"
                    class="flex items-center gap-2"
                    v-slot="{ errors, processing }"
                >
                    <InputError :message="errors.role" />
                    <SubmitButton
                        :processing="processing"
                        variant="ghost"
                        size="sm"
                        class="max-sm:h-11"
                        :aria-label="`Remove ${holder.name} from ${role.name}`"
                        >Remove</SubmitButton
                    >
                </Form>
            </template>
        </RoleHolderList>
    </section>
</template>
