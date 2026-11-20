<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings, UserRound } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import EmployeeInfo from '@/components/EmployeeInfo.vue';
import { logout } from '@/routes';
import { show as employeePage } from '@/routes/employees';
import { edit as security } from '@/routes/security';
import type { Employee } from '@/types';

/**
 * The user menu (rule L27): Your profile, the Employee's own Employee page,
 * which every Employee may open; Settings, which is Security; and Log out.
 */
type Props = {
    employee: Employee;
};

const handleLogout = () => {
    router.flushAll();
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <EmployeeInfo :employee="employee" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="w-full cursor-pointer max-sm:min-h-11"
                :href="employeePage(employee.id)"
            >
                <UserRound class="size-4" />
                Your profile
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="w-full cursor-pointer max-sm:min-h-11"
                :href="security()"
            >
                <Settings class="size-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="w-full cursor-pointer max-sm:min-h-11"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="size-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
