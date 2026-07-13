import { vi } from 'vitest';

// jsdom ships neither of these; components use them for the schedule day
// picker, so stub them to no-ops for the test environment.
if (!('IntersectionObserver' in globalThis)) {
    globalThis.IntersectionObserver = class {
        observe() {}
        unobserve() {}
        disconnect() {}
        takeRecords() {
            return [];
        }
    } as unknown as typeof IntersectionObserver;
}

if (!Element.prototype.scrollIntoView) {
    Element.prototype.scrollIntoView = vi.fn();
}
