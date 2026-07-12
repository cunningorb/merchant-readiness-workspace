import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import BenchmarkMethodologyDisclosure from '../BenchmarkMethodologyDisclosure.vue';

const methodology = 'These are configured, illustrative reference ranges, not measured industry data.';
const benchmarkVersion = '1.0';

let wrapper;

// attachTo is required so happy-dom computes v-show's display style for isVisible().
function mountDisclosure() {
    wrapper = mount(BenchmarkMethodologyDisclosure, {
        props: { methodology, benchmarkVersion },
        attachTo: document.body,
    });

    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
});

describe('BenchmarkMethodologyDisclosure', () => {
    it('is collapsed by default and hides the methodology panel', () => {
        const wrapper = mountDisclosure();
        const button = wrapper.get('button');

        expect(button.attributes('aria-expanded')).toBe('false');

        const panel = wrapper.get(`#${button.attributes('aria-controls')}`);
        expect(panel.isVisible()).toBe(false);
    });

    it('points aria-controls at the panel element id', () => {
        const wrapper = mountDisclosure();
        const button = wrapper.get('button');
        const controls = button.attributes('aria-controls');

        expect(controls).toBeTruthy();
        expect(wrapper.find(`#${controls}`).exists()).toBe(true);
    });

    it('expands on click, toggling aria-expanded and revealing the methodology and version', async () => {
        const wrapper = mountDisclosure();
        const button = wrapper.get('button');

        await button.trigger('click');

        expect(button.attributes('aria-expanded')).toBe('true');
        const panel = wrapper.get(`#${button.attributes('aria-controls')}`);
        expect(panel.isVisible()).toBe(true);
        expect(panel.text()).toContain(methodology);
        expect(panel.text()).toContain(benchmarkVersion);

        await button.trigger('click');
        expect(button.attributes('aria-expanded')).toBe('false');
        expect(panel.isVisible()).toBe(false);
    });

    it('uses a native button element so it is keyboard operable', () => {
        const wrapper = mountDisclosure();

        expect(wrapper.get('button').element.tagName).toBe('BUTTON');
    });
});
