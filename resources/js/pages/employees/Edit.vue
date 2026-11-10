<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeInvitationController from '@/actions/App/Http/Controllers/EmployeeInvitationController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/employees';
import type { Choice, EmployeeRecord } from '@/types';

const props = defineProps<{
    employee: EmployeeRecord;
    departments: Choice[];
    managers: Choice[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: props.employee.name, href: edit(props.employee.id) },
    ],
});
</script>

<template>
    <Head :title="employee.name" />

    <div class="flex max-w-xl flex-col gap-8 p-4">
        <Heading :title="employee.name" description="Employee record" />

        <div
            v-if="employee.has_pending_invitation"
            class="flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4"
        >
            <p class="text-sm text-muted-foreground">
                {{ employee.name }} hasn't set a password yet.
            </p>
            <Form
                v-bind="EmployeeInvitationController.store.form(employee.id)"
                :options="{ preserveScroll: true }"
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                >
                    Resend invitation
                </Button>
            </Form>
        </div>

        <Form
            v-bind="EmployeeController.update.form(employee.id)"
            :options="{ preserveScroll: true }"
            class="grid gap-6"
            v-slot="{ errors, processing }"
        >
            <EmployeeFields
                :employee="employee"
                :departments="departments"
                :managers="managers"
                :errors="errors"
            />

            <div>
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Save
                </Button>
            </div>
        </Form>
    </div>
</template>
