<script setup lang="ts">
import { Lightbulb } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * A Goal's Title and "What achieving it looks like" fields with the tip
 * on saying what done looks like, as the Add Goal and Edit Goal sheet
 * asks for them.
 */
defineProps<{
    errors: Partial<Record<'title' | 'description', string>>;
}>();

const title = defineModel<string>('title', { required: true });
const description = defineModel<string>('description', { required: true });
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-2">
            <Label for="goal-title">Title</Label>
            <Input
                id="goal-title"
                v-model="title"
                placeholder="e.g. Lead the Relaunch API work"
                maxlength="255"
                required
            />
            <InputError :message="errors.title" />
        </div>
        <div class="grid gap-2">
            <Label for="goal-description">What achieving it looks like</Label>
            <Textarea
                id="goal-description"
                v-model="description"
                class="min-h-36"
                maxlength="5000"
                required
            />
            <p
                class="flex items-start gap-2 text-caption text-muted-foreground"
            >
                <Lightbulb class="size-4 shrink-0" aria-hidden="true" />
                Say what done looks like by the end of the year, so you can both
                tell whether it was Met.
            </p>
            <InputError :message="errors.description" />
        </div>
    </div>
</template>
