<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { scopeLabel } from '@/lib/roles';
import type { Scope } from '@/types';

/**
 * A granted Permission's Scope: Own outlined, Reports lightly filled,
 * Everyone dark, so wider reach reads heavier.
 */
defineProps<{
    scope: Scope;
}>();

const variants = {
    own: 'outline',
    reports: 'secondary',
    everyone: 'default',
} as const;
</script>

<template>
    <Badge :variant="variants[scope]">{{ scopeLabel(scope) }}</Badge>
</template>
