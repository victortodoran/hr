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

const scopes: { value: Scope; label: string }[] = [
    { value: 'own', label: 'Own' },
    { value: 'reports', label: 'Reports' },
    { value: 'everyone', label: 'Everyone' },
];

const open = ref(false);

const form = useForm<{ name: string; permissions: Grants }>({
    name: props.role?.name ?? '',
    permissions: { ...props.role?.permissions },
});

function isGranted(permission: PermissionChoice): boolean {
    return permission.value in form.permissions;
}

function toggle(permission: PermissionChoice, granted: boolean): void {
    if (granted) {
        form.permissions[permission.value] = permission.scoped ? 'own' : null;
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
                            v-if="permission.scoped && isGranted(permission)"
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
                                    v-for="scope in scopes"
                                    :key="scope.value"
                                    :value="scope.value"
                                >
                                    {{ scope.label }}
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
