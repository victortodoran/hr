<script setup lang="ts">
import Callout from '@/components/Callout.vue';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { noManager } from '@/lib/employees';
import type { Choice, EmploymentStatus, ManagerChoice } from '@/types';

/**
 * An Employee's record in the form template's sections (rule L13):
 * Person (name and email), Organisation (Department and Manager, with
 * what "No Manager" means as a Callout once it's chosen, K12) and
 * Employment (Start and Leaving Date side by side, V6, and the Job Title
 * when adding one). A Former Employee's Leaving Date has passed, so it
 * can't be changed.
 */
const props = defineProps<{
    departments: Choice[];
    managers: ManagerChoice[];
    errors: Partial<Record<string, string>>;
    /** The Employee's status when editing them; left out when adding. */
    status?: EmploymentStatus;
}>();

const name = defineModel<string>('name', { required: true });
const email = defineModel<string>('email', { required: true });
const departmentId = defineModel<string>('departmentId', { required: true });
const managerId = defineModel<string>('managerId', { required: true });
const startDate = defineModel<string>('startDate', { required: true });
const leavingDate = defineModel<string>('leavingDate', { required: true });
/** Asked for only when adding an Employee. */
const jobTitle = defineModel<string>('jobTitle');

const hasLeft = props.status === 'former';
</script>

<template>
    <div class="flex flex-col gap-8">
        <section class="flex flex-col gap-5" aria-labelledby="person-heading">
            <h2 id="person-heading" class="text-section">Person</h2>

            <div class="grid gap-2">
                <Label for="name">Full name</Label>
                <Input
                    id="name"
                    v-model="name"
                    name="name"
                    required
                    maxlength="255"
                    autocomplete="off"
                    :aria-invalid="errors.name ? true : undefined"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="email"
                    type="email"
                    name="email"
                    required
                    maxlength="255"
                    autocomplete="off"
                    :aria-invalid="errors.email ? true : undefined"
                />
                <InputError :message="errors.email" />
            </div>
        </section>

        <section
            class="flex flex-col gap-5"
            aria-labelledby="organisation-heading"
        >
            <h2 id="organisation-heading" class="text-section">Organisation</h2>

            <div class="grid gap-5 sm:grid-cols-2 sm:gap-4">
                <div class="grid content-start gap-2">
                    <Label for="department_id">Department</Label>
                    <Select
                        v-model="departmentId"
                        name="department_id"
                        required
                    >
                        <SelectTrigger
                            id="department_id"
                            class="w-full"
                            :aria-invalid="
                                errors.department_id ? true : undefined
                            "
                        >
                            <SelectValue placeholder="Choose a Department" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="department in departments"
                                :key="department.id"
                                :value="String(department.id)"
                            >
                                {{ department.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.department_id" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="manager_id">Manager</Label>
                    <Select v-model="managerId" name="manager_id" required>
                        <SelectTrigger
                            id="manager_id"
                            class="w-full"
                            :aria-invalid="errors.manager_id ? true : undefined"
                        >
                            <SelectValue placeholder="Choose a Manager" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="noManager"
                                >No Manager</SelectItem
                            >
                            <SelectItem
                                v-for="manager in managers"
                                :key="manager.id"
                                :value="String(manager.id)"
                            >
                                {{ manager.name }}
                                <span
                                    v-if="manager.status === 'future'"
                                    class="text-muted-foreground"
                                >
                                    (Future Employee)
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.manager_id" />
                </div>
            </div>

            <Callout
                v-if="managerId === noManager"
                tone="info"
                data-test="no-manager"
            >
                With no Manager, nobody decides for
                {{ name.trim() || 'them' }}: Time Off is Approved at once and
                they record no Work Entries.
            </Callout>
        </section>

        <section
            class="flex flex-col gap-5"
            aria-labelledby="employment-heading"
        >
            <h2 id="employment-heading" class="text-section">Employment</h2>

            <div class="grid gap-5 sm:grid-cols-2 sm:gap-4">
                <div class="grid content-start gap-2">
                    <Label for="start_date">Start Date</Label>
                    <DatePicker
                        id="start_date"
                        v-model="startDate"
                        required
                        :invalid="Boolean(errors.start_date)"
                    />
                    <InputError :message="errors.start_date" />
                </div>

                <div class="grid content-start gap-2">
                    <Label for="leaving_date"
                        >Leaving Date
                        <span
                            v-if="!hasLeft"
                            class="font-normal text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <DatePicker
                        id="leaving_date"
                        v-model="leavingDate"
                        :min="startDate || undefined"
                        :disabled="hasLeft"
                        :invalid="Boolean(errors.leaving_date)"
                    />
                    <InputError
                        v-if="errors.leaving_date"
                        :message="errors.leaving_date"
                    />
                    <p
                        v-else-if="hasLeft"
                        class="text-caption text-muted-foreground"
                    >
                        This Leaving Date has passed, so it can't be changed.
                    </p>
                    <p v-else class="text-caption text-muted-foreground">
                        Their last working day; they can't sign in after it.
                    </p>
                </div>
            </div>

            <div v-if="jobTitle !== undefined" class="grid gap-2">
                <Label for="job_title">Job Title</Label>
                <Input
                    id="job_title"
                    v-model="jobTitle"
                    name="job_title"
                    required
                    maxlength="255"
                    autocomplete="off"
                    placeholder="e.g. Developer"
                    :aria-invalid="errors.job_title ? true : undefined"
                />
                <InputError
                    v-if="errors.job_title"
                    :message="errors.job_title"
                />
                <p v-else class="text-caption text-muted-foreground">
                    Shown in the Directory. Edit it later on the Assignments
                    tab.
                </p>
            </div>
        </section>
    </div>
</template>
