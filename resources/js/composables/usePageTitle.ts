import type { Ref } from 'vue';
import { ref } from 'vue';

const pageTitle = ref<string | null>(null);

export function usePageTitle(): Ref<string | null> {
    return pageTitle;
}
