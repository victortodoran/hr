<script setup lang="ts">
import { Camera, Trash2, Upload } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import IconButton from '@/components/IconButton.vue';
import { Button } from '@/components/ui/button';

/** A stored file has no Remove: where a file is stored it is required (a Travel Expense always has its receipt). */
const props = defineProps<{
    id: string;
    accept: string;
    /** In MB. */
    maxMb: number;
    current?: { name: string; url: string; size?: number | null } | null;
    noun?: string;
    invalid?: boolean;
}>();

const file = defineModel<File | null>({ default: null });

const fileInput = ref<HTMLInputElement | null>(null);
const cameraInput = ref<HTMLInputElement | null>(null);
/** Moving between child elements fires `dragleave` before the next `dragenter`, so the drag is over the field until the count drops back to 0. */
const dragDepth = ref(0);
const isDraggingOver = computed(() => dragDepth.value > 0);

const typesLabel = computed(() => {
    const types = [
        ...new Set(
            props.accept
                .split(',')
                .map((type) => type.trim().replace(/^\./, '').split('/').pop())
                .filter((type): type is string => !!type && type !== '*')
                .map((type) => (type === 'jpeg' ? 'JPG' : type.toUpperCase())),
        ),
    ];

    return types.length > 1
        ? `${types.slice(0, -1).join(', ')} or ${types.at(-1)}`
        : (types[0] ?? 'Any file');
});

const helpText = computed(() => {
    const text = `${typesLabel.value}, up to ${props.maxMb} MB.`;

    return shown.value ? `${text} Drop a new file here to replace it.` : text;
});

const shown = computed(() => {
    if (file.value) {
        return { name: file.value.name, size: file.value.size, url: null };
    }

    return props.current
        ? {
              name: props.current.name,
              size: props.current.size ?? null,
              url: props.current.url,
          }
        : null;
});

const isImage = computed(() =>
    file.value
        ? file.value.type.startsWith('image/')
        : /\.(jpe?g|png|gif|webp|heic)$/i.test(props.current?.name ?? ''),
);

const extension = computed(
    () => shown.value?.name.split('.').pop()?.toUpperCase().slice(0, 4) ?? '',
);

const previewUrl = ref<string | null>(null);

watch(
    file,
    (chosen) => {
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
        }

        previewUrl.value =
            chosen && chosen.type.startsWith('image/')
                ? URL.createObjectURL(chosen)
                : null;
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

const thumbnailUrl = computed(() =>
    file.value ? previewUrl.value : isImage.value ? props.current?.url : null,
);

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function choose(event: Event): void {
    const input = event.target as HTMLInputElement;
    const chosen = input.files?.[0];

    if (chosen) {
        file.value = chosen;
    }

    input.value = '';
}

function drop(event: DragEvent): void {
    dragDepth.value = 0;
    const dropped = event.dataTransfer?.files[0];

    if (dropped) {
        file.value = dropped;
    }
}

function browse(): void {
    fileInput.value?.click();
}
</script>

<template>
    <div class="grid gap-1.5">
        <div
            data-slot="file-drop"
            :aria-invalid="invalid || undefined"
            class="rounded-lg border border-dashed border-border-strong p-3 transition-colors aria-invalid:border-destructive"
            :class="[
                isDraggingOver && 'border-foreground bg-surface',
                shown && 'border-solid',
            ]"
            @dragenter.prevent="dragDepth++"
            @dragover.prevent
            @dragleave.prevent="dragDepth = Math.max(0, dragDepth - 1)"
            @drop.prevent="drop"
        >
            <input
                :id="id"
                ref="fileInput"
                type="file"
                class="sr-only"
                :accept="accept"
                @change="choose"
            />
            <input
                :id="`${id}-camera`"
                ref="cameraInput"
                type="file"
                class="sr-only"
                accept="image/*"
                capture="environment"
                tabindex="-1"
                aria-hidden="true"
                @change="choose"
            />

            <div
                v-if="shown"
                class="flex items-center gap-3 max-sm:flex-wrap"
                data-test="file-drop-file"
            >
                <img
                    v-if="thumbnailUrl"
                    :src="thumbnailUrl"
                    alt=""
                    class="size-10 shrink-0 rounded-md border object-cover"
                />
                <span
                    v-else
                    aria-hidden="true"
                    class="flex size-10 shrink-0 items-center justify-center rounded-md border bg-surface text-caption font-medium text-muted-foreground"
                    >{{ extension }}</span
                >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-body font-medium">
                        {{ shown.name }}
                    </p>
                    <p class="text-meta text-muted-foreground">
                        <template v-if="shown.size">{{
                            formatSize(shown.size)
                        }}</template>
                        <template v-if="shown.size && shown.url"> · </template>
                        <a
                            v-if="shown.url"
                            :href="shown.url"
                            target="_blank"
                            rel="noopener"
                            class="text-foreground underline decoration-border-strong underline-offset-4 hover:decoration-current"
                            >Open</a
                        >
                    </p>
                </div>
                <div class="flex items-center gap-1 max-sm:hidden">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="browse"
                        >Replace</Button
                    >
                    <IconButton
                        v-if="file"
                        :label="`Remove ${noun ?? 'file'}`"
                        @click="file = null"
                    >
                        <Trash2 />
                    </IconButton>
                </div>
                <IconButton
                    v-if="file"
                    class="sm:hidden"
                    :label="`Remove ${noun ?? 'file'}`"
                    @click="file = null"
                >
                    <Trash2 />
                </IconButton>
            </div>

            <div
                v-else
                class="flex items-center gap-3 text-body text-muted-foreground max-sm:hidden"
            >
                <Upload class="size-4 shrink-0" aria-hidden="true" />
                <span class="flex-1">Drop a file here, or</span>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="browse"
                    >Choose file</Button
                >
            </div>

            <div
                class="flex gap-2 sm:hidden"
                :class="shown && 'mt-3'"
                data-test="file-drop-phone"
            >
                <Button
                    type="button"
                    variant="secondary"
                    class="h-11 flex-1"
                    @click="cameraInput?.click()"
                >
                    <Camera />
                    Take photo
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    class="h-11 flex-1"
                    @click="browse"
                >
                    <Upload />
                    Choose file
                </Button>
            </div>
        </div>
        <p class="text-meta text-muted-foreground">{{ helpText }}</p>
    </div>
</template>
