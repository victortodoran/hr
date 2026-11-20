import type { Ref } from 'vue';
import { ref } from 'vue';

/** The current page's `<h1>`, set by its PageHeader. */
const pageTitle = ref<string | null>(null);

/**
 * The current page's heading, for the phone top bar (rule P2), so the
 * bar always names the page the way its `<h1>` does.
 */
export function usePageTitle(): Ref<string | null> {
    return pageTitle;
}
