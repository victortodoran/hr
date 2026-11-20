<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <textarea
    v-model="modelValue"
    data-slot="textarea"
    :class="cn(
      'placeholder:text-subtle-foreground border-border-strong flex field-sizing-content min-h-16 w-full min-w-0 rounded-md border bg-background px-3 py-2 text-base transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-body',
      'focus-visible:border-primary focus-visible:ring-ring/25 focus-visible:ring-[3px]',
      'aria-invalid:ring-destructive/15 aria-invalid:border-destructive',
      props.class,
    )"
  />
</template>
