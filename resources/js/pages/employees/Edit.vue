<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import EmployeeInvitationController from '@/actions/App/Http/Controllers/EmployeeInvitationController';
import EmployeeFields from '@/components/employees/EmployeeFields.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/employees';
import type { Choice, EmployeeRecord, ManagerChoice } from '@/types';

const props = defineProps<{
    employee: EmployeeRecord;
    departments: Choice[];
    managers: ManagerChoice[];
}>();

const statusBadges = {
    future: { label: 'Future Employee', variant: 'secondary' },
    current: { label: 'Current Employee', variant: 'default' },
    former: { label: 'Former Employee', variant: 'outline' },
} as const;

setLayoutProps({
    breadcrumbs: [
        { title: props.employee.name, href: edit(props.employee.id) },
    ],
});
</script>

<template>
    <Head :title="employee.name" />

    <div class="flex max-w-xl flex-col gap-8 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading :title="employee.name" description="Employee record" />
            <Badge :variant="statusBadges[employee.status].variant">
                {{ statusBadges[employee.status].label }}
            </Badge>
        </div>

        <div
            v-if="employee.can_resend_invitation"
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
