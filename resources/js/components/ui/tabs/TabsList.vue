<script setup lang="ts">
import type { TabsListProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit, unrefElement, useEventListener, useResizeObserver } from "@vueuse/core"
import { TabsList } from "reka-ui"
import { computed, ref, useTemplateRef } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<TabsListProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

/**
 * Whether more tabs sit past the right edge, so the strip fades there
 * as a hint to scroll (rule P5).
 */
const list = useTemplateRef("list")
const element = computed(() => unrefElement(list.value) as HTMLElement | undefined)
const hasMoreToTheRight = ref(false)

function measure(): void {
  const strip = element.value
  hasMoreToTheRight.value = strip !== undefined
    && strip.scrollLeft + strip.clientWidth < strip.scrollWidth - 1
}

useResizeObserver(element, measure)
useEventListener(element, "scroll", measure, { passive: true })
</script>

<template>
  <TabsList
    ref="list"
    data-slot="tabs-list"
    :data-more-right="hasMoreToTheRight ? '' : undefined"
    v-bind="delegatedProps"
    :class="cn(
      'text-muted-foreground flex h-10 w-fit max-w-full items-end justify-start gap-6 overflow-x-auto border-b border-border [scrollbar-width:none] data-[more-right]:[mask-image:linear-gradient(to_right,black_calc(100%-2.5rem),transparent)]',
      props.class,
    )"
  >
    <slot />
  </TabsList>
</template>
