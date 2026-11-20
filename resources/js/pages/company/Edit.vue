<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import CompanySettingsController from '@/actions/App/Http/Controllers/Company/CompanySettingsController';
import FactList from '@/components/FactList.vue';
import InputError from '@/components/InputError.vue';
import SavedTick from '@/components/SavedTick.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import CompanySettingsLayout from '@/layouts/settings/CompanySettingsLayout.vue';
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

    <CompanySettingsLayout>
        <section
            aria-labelledby="company-heading"
            class="flex max-w-xl flex-col gap-4"
        >
            <header>
                <h2 id="company-heading" class="text-section">Company</h2>
                <p class="text-body text-muted-foreground">
                    The company's name and the currency every Pay and Travel
                    Expense is recorded in.
                </p>
            </header>

            <Form
                v-bind="CompanySettingsController.update.form()"
                :options="{ preserveScroll: true }"
                set-defaults-on-success
                class="flex flex-col gap-6"
                v-slot="{
                    errors,
                    processing,
                    isDirty,
                    reset,
                    recentlySuccessful,
                }"
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

                <template v-if="isCurrencyLocked">
                    <input
                        type="hidden"
                        name="currency"
                        :value="company.currency"
                    />
                    <FactList
                        :facts="[
                            { label: 'Currency', value: company.currency },
                        ]"
                    >
                        <template #fact-Currency>
                            {{ company.currency }}
                            <p class="text-caption text-muted-foreground">
                                Locked, as Pay or Travel Expenses are recorded
                                in it.
                            </p>
                        </template>
                    </FactList>
                </template>
                <div v-else class="grid gap-2">
                    <Label for="company-currency">Currency</Label>
                    <Input
                        id="company-currency"
                        name="currency"
                        :default-value="company.currency"
                        maxlength="3"
                        class="w-24 uppercase"
                        aria-describedby="company-currency-help"
                        required
                    />
                    <p
                        id="company-currency-help"
                        class="text-caption text-muted-foreground"
                    >
                        A three-letter code such as EUR, locked once any Pay or
                        Travel Expense is recorded in it.
                    </p>
                    <InputError :message="errors.currency" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <SubmitButton :processing="processing">Save</SubmitButton>
                    <Button
                        type="button"
                        variant="secondary"
                        :disabled="!isDirty"
                        @click="reset()"
                        >Cancel</Button
                    >
                    <SavedTick :shown="recentlySuccessful" />
                </div>
            </Form>
        </section>
    </CompanySettingsLayout>
</template>
