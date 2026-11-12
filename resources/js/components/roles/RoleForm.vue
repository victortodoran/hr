<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Grants, PermissionChoice, RoleRecord, Scope } from '@/types';

const props = defineProps<{
    permissions: PermissionChoice[];
    role?: RoleRecord;
}>();

const scopeLabels: Record<Scope, string> = {
    own: 'Own',
    reports: 'Reports',
    everyone: 'Everyone',
};

const open = ref(false);

const form = useForm<{
    name: string;
    permissions: Grants;
    given_to_new_employees: boolean;
}>({
    name: props.role?.name ?? '',
    permissions: { ...props.role?.permissions },
    given_to_new_employees: props.role?.given_to_new_employees ?? false,
});

function isGranted(permission: PermissionChoice): boolean {
    return permission.value in form.permissions;
}

function toggle(permission: PermissionChoice, granted: boolean): void {
    if (granted) {
        form.permissions[permission.value] = permission.scopes[0] ?? null;
    } else {
        delete form.permissions[permission.value];
    }
}

function submit(): void {
    const action = props.role
        ? RoleController.update(props.role.id)
        : RoleController.store();

    form.submit(action, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;

            if (!props.role) {
                form.reset();
            }
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button v-if="role" variant="outline" size="sm">Edit</Button>
            <Button v-else>New Role</Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90vh] overflow-y-auto">
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{
                        role ? `Edit ${role.name}` : 'New Role'
                    }}</DialogTitle>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="`role-name-${role?.id ?? 'new'}`">Name</Label>
                    <Input
                        :id="`role-name-${role?.id ?? 'new'}`"
                        v-model="form.name"
                        placeholder="e.g. Payroll"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label class="flex items-center gap-3 font-normal">
                        <Checkbox
                            :model-value="form.given_to_new_employees"
                            @update:model-value="
                                form.given_to_new_employees = $event === true
                            "
                        />
                        Give to new Employees
                    </Label>
                    <InputError :message="form.errors.given_to_new_employees" />
                </div>

                <fieldset class="grid gap-3">
                    <legend class="mb-2 text-sm font-medium">
                        Permissions
                    </legend>
                    <div
                        v-for="permission in permissions"
                        :key="permission.value"
                        class="flex min-h-9 items-center justify-between gap-4"
                    >
                        <Label class="flex items-center gap-3 font-normal">
                            <Checkbox
                                :model-value="isGranted(permission)"
                                @update:model-value="
                                    toggle(permission, $event === true)
                                "
                            />
                            {{ permission.label }}
                        </Label>
                        <Select
                            v-if="
                                permission.scopes.length > 0 &&
                                isGranted(permission)
                            "
                            :model-value="form.permissions[permission.value]!"
                            @update:model-value="
                                form.permissions[permission.value] =
                                    $event as Scope
                            "
                        >
                            <SelectTrigger
                                class="w-32"
                                :aria-label="`Scope of ${permission.label}`"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="scope in permission.scopes"
                                    :key="scope"
                                    :value="scope"
                                >
                                    {{ scopeLabels[scope] }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <InputError :message="form.errors.permissions" />
                </fieldset>

                <InputError
                    :message="(form.errors as Record<string, string>).role"
                />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" type="button"
                            >Cancel</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing"
                        >Save</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
