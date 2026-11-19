<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CompanySettingsController from '@/actions/App/Http/Controllers/Company/CompanySettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/company';
import type { CompanySettings } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Company', href: edit() }],
    },
});

defineProps<{
    company: CompanySettings;
    isCurrencyLocked: boolean;
}>();
</script>

<template>
    <Head title="Company" />

    <div class="mx-auto flex w-full max-w-xl flex-col gap-8 p-4">
        <Heading
            title="Company"
            description="The company's name and the currency every Pay and Travel Expense is recorded in"
        />

        <Form
            v-bind="CompanySettingsController.update.form()"
            :options="{ preserveScroll: true }"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="company-name">Name</Label>
                <Input
                    id="company-name"
                    name="name"
                    :default-value="company.name"
                    required
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="company-currency">Currency</Label>
                <Input
                    id="company-currency"
                    name="currency"
                    :default-value="company.currency"
                    maxlength="3"
                    class="uppercase"
                    :readonly="isCurrencyLocked"
                    required
                />
                <p class="text-sm text-muted-foreground">
                    {{
                        isCurrencyLocked
                            ? "Pay or Travel Expenses have been recorded in this currency, so it can't be changed."
                            : 'A three-letter code, such as EUR. It can only be changed until Pay or Travel Expenses are recorded.'
                    }}
                </p>
                <InputError :message="errors.currency" />
            </div>

            <SubmitButton :processing="processing">Save</SubmitButton>
        </Form>
    </div>
</template>
