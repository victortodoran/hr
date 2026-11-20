<script setup lang="ts">
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
import { noClient } from '@/lib/projects';
import type { Choice } from '@/types';

/**
 * A Project's Name, Client and dates, as the New Project and Edit Project
 * sheets both ask for them; the dates through DatePickers (K14).
 */
defineProps<{
    clients: Choice[];
    errors: Partial<
        Record<'name' | 'client_id' | 'start_date' | 'end_date', string>
    >;
}>();

const name = defineModel<string>('name', { required: true });
const clientId = defineModel<string>('clientId', { required: true });
const startDate = defineModel<string>('startDate', { required: true });
const endDate = defineModel<string>('endDate', { required: true });
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="grid gap-2">
            <Label for="project-name">Name</Label>
            <Input
                id="project-name"
                v-model="name"
                placeholder="e.g. Website relaunch"
                maxlength="255"
                required
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="project-client">Client</Label>
            <Select v-model="clientId">
                <SelectTrigger id="project-client" class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="noClient">No Client</SelectItem>
                    <SelectItem
                        v-for="client in clients"
                        :key="client.id"
                        :value="String(client.id)"
                    >
                        {{ client.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.client_id" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="project-start">Start date</Label>
                <DatePicker
                    id="project-start"
                    v-model="startDate"
                    required
                    :invalid="Boolean(errors.start_date)"
                />
                <InputError :message="errors.start_date" />
            </div>
            <div class="grid gap-2">
                <Label for="project-end">End date (optional)</Label>
                <DatePicker
                    id="project-end"
                    v-model="endDate"
                    :min="startDate || undefined"
                    :invalid="Boolean(errors.end_date)"
                />
                <InputError :message="errors.end_date" />
            </div>
        </div>
    </div>
</template>
