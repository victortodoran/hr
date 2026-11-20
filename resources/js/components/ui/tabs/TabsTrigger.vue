<script setup lang="ts">
import type { TabsTriggerProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { TabsTrigger, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<TabsTriggerProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <TabsTrigger
    data-slot="tabs-trigger"
    v-bind="forwardedProps"
    :class="cn(
      'text-muted-foreground hover:text-foreground data-[state=active]:text-foreground data-[state=active]:border-primary focus-visible:ring-ring/40 -mb-px inline-flex h-10 shrink-0 items-center justify-center gap-1.5 border-b-2 border-transparent text-body font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-50 [&_[data-slot=badge]]:bg-muted [&_[data-slot=badge]]:text-muted-foreground data-[state=active]:[&_[data-slot=badge]]:bg-primary-soft data-[state=active]:[&_[data-slot=badge]]:text-primary [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=\'size-\'])]:size-4',
      props.class,
    )"
  >
    <slot />
  </TabsTrigger>
</template>
