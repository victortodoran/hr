<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import GoalController from '@/actions/App/Http/Controllers/Performance/GoalController';
import SubmitButton from '@/components/SubmitButton.vue';
import GoalFields from '@/components/performance-evaluations/GoalFields.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { show } from '@/routes/performance-evaluations';

/**
 * Gives a Performance Evaluation a Goal. Giving and cancelling lead back
 * to the Performance Evaluation's page.
 */
const props = defineProps<{
    /** The Performance Evaluation the Goal is on. */
    evaluationId: number;
}>();

const form = useForm({
    title: '',
    description: '',
});

function give(): void {
    form.submit(GoalController.store(props.evaluationId));
}
</script>

<template>
    <form @submit.prevent="give">
        <Card>
            <CardContent>
                <GoalFields
                    v-model:title="form.title"
                    v-model:description="form.description"
                    :errors="form.errors"
                />
            </CardContent>
            <CardFooter class="gap-2 border-t">
                <SubmitButton :processing="form.processing"
                    >Give Goal</SubmitButton
                >
                <Button variant="ghost" as-child>
                    <Link :href="show(evaluationId)">Cancel</Link>
                </Button>
            </CardFooter>
        </Card>
    </form>
</template>
