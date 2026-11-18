<script setup lang="ts">
import type { SelectRootEmits, SelectRootProps } from "reka-ui"
import { SelectRoot, useForwardPropsEmits } from "reka-ui"

/**
 * A dropdown. Given a `name` inside a form, reka renders a hidden native
 * select that joins the browser's validation, so `required` stops an empty
 * submit. Whenever a `name` is given, a positioned wrapper anchors the
 * browser's message beside the dropdown instead of the page's top-left.
 */
const props = defineProps<SelectRootProps>()
const emits = defineEmits<SelectRootEmits>()

const forwarded = useForwardPropsEmits(props, emits)
</script>

<template>
  <div v-if="props.name" class="relative">
    <SelectRoot
      v-slot="slotProps"
      data-slot="select"
      v-bind="forwarded"
    >
      <slot v-bind="slotProps" />
    </SelectRoot>
  </div>
  <SelectRoot
    v-else
    v-slot="slotProps"
    data-slot="select"
    v-bind="forwarded"
  >
    <slot v-bind="slotProps" />
  </SelectRoot>
</template>
