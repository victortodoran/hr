<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Briefcase, FolderKanban, Search } from '@lucide/vue';
import { useEventListener, watchDebounced } from '@vueuse/core';
import {
    ComboboxContent,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxLabel,
    ComboboxRoot,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import IconButton from '@/components/IconButton.vue';
import PersonCell from '@/components/PersonCell.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useNavItems } from '@/composables/useNavItems';
import { toUrl } from '@/lib/utils';
import { search as searchRoute } from '@/routes';
import { show as clientPage } from '@/routes/clients';
import { index as directory } from '@/routes/directory';
import { show as employeePage } from '@/routes/employees';
import { show as projectPage } from '@/routes/projects';
import type { CommandMenuResults } from '@/types';

/**
 * Search from any page (rules L26, K22): ⌘K or Ctrl+K, or the top bar's
 * search button, opens a Dialog holding a Combobox over people, Projects,
 * Clients and the pages the sidebar offers. Records come from the server,
 * only those the viewer could already open; pages are the sidebar's own
 * entries. Choosing a result opens it.
 */
type Choice = {
    key: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

const open = ref(false);
const term = ref('');

const noResults: CommandMenuResults = { people: [], projects: [], clients: [] };
const results = ref<CommandMenuResults>(noResults);

const http = useHttp<{ search: string }, CommandMenuResults>({ search: '' });

const { startItems, areaItems, companyItems } = useNavItems();

/** The shortcut as this computer writes it. */
const shortcut =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.userAgent)
        ? '⌘K'
        : 'Ctrl K';

useEventListener('keydown', (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        open.value = !open.value;
    }
});

watchDebounced(
    term,
    (value) => {
        http.cancel();

        if (value.trim() === '') {
            results.value = noResults;

            return;
        }

        http.search = value;
        http.get(searchRoute.url(), {
            onSuccess: (found) => {
                results.value = found;
            },
        }).catch(() => undefined);
    },
    { debounce: 150 },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        http.cancel();
        term.value = '';
        results.value = noResults;
    }
});

/** The sidebar's pages whose name holds the search; all of them before one is typed. */
const pages = computed(() => {
    const wanted = term.value.trim().toLowerCase();

    return [
        ...startItems.value,
        ...areaItems.value,
        ...companyItems.value,
    ].filter((item) => item.title.toLowerCase().includes(wanted));
});

/*
 * Each group's choices, built once: the template renders these, so an
 * item's value always matches the choice it opens.
 */
const personChoices = computed(() =>
    results.value.people.map((person) => ({
        key: `person-${person.id}`,
        href: person.canViewEmployee
            ? employeePage(person.id)
            : directory({ query: { person: person.id } }),
        person,
    })),
);

const projectChoices = computed(() =>
    results.value.projects.map((project) => ({
        key: `project-${project.id}`,
        href: projectPage(project.id),
        project,
    })),
);

const clientChoices = computed(() =>
    results.value.clients.map((client) => ({
        key: `client-${client.id}`,
        href: clientPage(client.id),
        client,
    })),
);

const pageChoices = computed(() =>
    pages.value.map((page) => ({
        key: `page-${toUrl(page.href)}`,
        href: page.href,
        page,
    })),
);

const choices = computed<Choice[]>(() => [
    ...personChoices.value,
    ...projectChoices.value,
    ...clientChoices.value,
    ...pageChoices.value,
]);

const nothingFound = computed(
    () =>
        term.value.trim() !== '' &&
        !http.processing &&
        choices.value.length === 0,
);

function choose(key: unknown): void {
    const choice = choices.value.find((each) => each.key === key);

    if (!choice) {
        return;
    }

    open.value = false;
    router.visit(choice.href);
}

const itemClass =
    'flex min-h-11 cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-body outline-none select-none data-highlighted:bg-accent sm:min-h-9';
const labelClass =
    'px-2 pt-2 pb-1 text-caption font-medium text-muted-foreground';
</script>

<template>
    <div class="ml-auto flex items-center" data-slot="command-menu">
        <button
            type="button"
            class="hidden h-8 w-70 items-center gap-2 rounded-md border border-border px-2.5 text-meta text-subtle-foreground transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:flex"
            data-test="command-menu-button"
            @click="open = true"
        >
            <Search class="size-4" aria-hidden="true" />
            Search people, Projects…
            <kbd
                class="ml-auto rounded border border-b-2 border-border-strong bg-background px-1 font-sans text-caption text-muted-foreground"
                >{{ shortcut }}</kbd
            >
        </button>
        <IconButton
            label="Search…"
            class="size-11 sm:hidden"
            data-test="command-menu-icon"
            @click="open = true"
        >
            <Search />
        </IconButton>

        <Dialog v-model:open="open">
            <DialogContent
                :show-close-button="false"
                class="gap-0 overflow-hidden p-0 max-sm:pb-0 sm:top-[12%] sm:max-w-xl sm:translate-y-0"
                data-test="command-menu"
            >
                <DialogTitle class="sr-only">Search</DialogTitle>
                <DialogDescription class="sr-only">
                    Find people, Projects, Clients and pages, then pick one to
                    open it.
                </DialogDescription>
                <ComboboxRoot
                    :open="true"
                    ignore-filter
                    :reset-search-term-on-blur="false"
                    :reset-search-term-on-select="false"
                    @update:model-value="choose"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border px-4"
                    >
                        <Search
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <ComboboxInput
                            v-model="term"
                            auto-focus
                            aria-label="Search people, Projects, Clients and pages"
                            placeholder="Search people, Projects, Clients and pages"
                            class="h-12 w-full min-w-0 bg-transparent text-body outline-none placeholder:text-subtle-foreground"
                        />
                    </div>
                    <ComboboxContent
                        class="max-h-[min(60dvh,26rem)] overflow-y-auto p-2 max-sm:pb-[max(0.5rem,env(safe-area-inset-bottom))]"
                        @escape-key-down="open = false"
                    >
                        <ComboboxViewport>
                            <p
                                v-if="nothingFound"
                                class="px-2 py-6 text-center text-body text-muted-foreground"
                            >
                                Nothing matches “{{ term.trim() }}”. Try part of
                                a name.
                            </p>

                            <ComboboxGroup v-if="personChoices.length > 0">
                                <ComboboxLabel :class="labelClass"
                                    >People</ComboboxLabel
                                >
                                <ComboboxItem
                                    v-for="{ key, person } in personChoices"
                                    :key="key"
                                    :value="key"
                                    :text-value="person.name"
                                    :class="itemClass"
                                >
                                    <PersonCell
                                        :name="person.name"
                                        :detail="person.job_title"
                                    />
                                </ComboboxItem>
                            </ComboboxGroup>

                            <ComboboxGroup v-if="projectChoices.length > 0">
                                <ComboboxLabel :class="labelClass"
                                    >Projects</ComboboxLabel
                                >
                                <ComboboxItem
                                    v-for="{ key, project } in projectChoices"
                                    :key="key"
                                    :value="key"
                                    :text-value="project.name"
                                    :class="itemClass"
                                >
                                    <FolderKanban
                                        class="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        project.name
                                    }}</span>
                                    <span
                                        v-if="project.client"
                                        class="ml-auto truncate text-meta text-muted-foreground"
                                        >{{ project.client }}</span
                                    >
                                </ComboboxItem>
                            </ComboboxGroup>

                            <ComboboxGroup v-if="clientChoices.length > 0">
                                <ComboboxLabel :class="labelClass"
                                    >Clients</ComboboxLabel
                                >
                                <ComboboxItem
                                    v-for="{ key, client } in clientChoices"
                                    :key="key"
                                    :value="key"
                                    :text-value="client.name"
                                    :class="itemClass"
                                >
                                    <Briefcase
                                        class="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        client.name
                                    }}</span>
                                </ComboboxItem>
                            </ComboboxGroup>

                            <ComboboxGroup v-if="pageChoices.length > 0">
                                <ComboboxLabel :class="labelClass"
                                    >Pages</ComboboxLabel
                                >
                                <ComboboxItem
                                    v-for="{ key, page } in pageChoices"
                                    :key="key"
                                    :value="key"
                                    :text-value="page.title"
                                    :class="itemClass"
                                >
                                    <component
                                        :is="page.icon"
                                        class="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <span class="truncate">{{
                                        page.title
                                    }}</span>
                                </ComboboxItem>
                            </ComboboxGroup>
                        </ComboboxViewport>
                    </ComboboxContent>
                </ComboboxRoot>
            </DialogContent>
        </Dialog>
    </div>
</template>
