import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import RecommendationsDisclosure from '../RecommendationsDisclosure.vue';

const recommendations = [
    {
        title: 'Publish a returns FAQ',
        description: 'Answer common questions before they hit support.',
        category: 'return_policy',
        priority: 'medium',
        expected_impact: 'Fewer support contacts',
        opportunity_type: null,
    },
    {
        title: 'Audit carrier performance',
        description: 'Review return shipping speed by carrier.',
        category: 'platform',
        priority: 'low',
        expected_impact: 'Faster refund cycles',
        opportunity_type: null,
    },
];

let wrapper;

// attachTo is required so happy-dom computes v-show's display style for isVisible().
function mountDisclosure() {
    wrapper = mount(RecommendationsDisclosure, {
        props: { recommendations },
        attachTo: document.body,
    });

    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
});

describe('RecommendationsDisclosure', () => {
    it('is collapsed by default and hides its recommendations', () => {
        const wrapper = mountDisclosure();
        const button = wrapper.get('button');

        expect(button.attributes('aria-expanded')).toBe('false');

        const panel = wrapper.get(`#${button.attributes('aria-controls')}`);
        expect(panel.isVisible()).toBe(false);
    });

    it('shows the recommendation count in the toggle label', () => {
        const wrapper = mountDisclosure();

        expect(wrapper.get('button').text()).toContain('View all 2 recommendations');
    });

    it('expands on click, toggling aria-expanded and revealing the panel', async () => {
        const wrapper = mountDisclosure();
        const button = wrapper.get('button');

        await button.trigger('click');

        expect(button.attributes('aria-expanded')).toBe('true');
        const panel = wrapper.get(`#${button.attributes('aria-controls')}`);
        expect(panel.isVisible()).toBe(true);
        expect(panel.text()).toContain('Publish a returns FAQ');
        expect(panel.text()).toContain('Audit carrier performance');

        await button.trigger('click');
        expect(button.attributes('aria-expanded')).toBe('false');
        expect(panel.isVisible()).toBe(false);
    });

    it('points aria-controls at the panel element id', () => {
        const wrapper = mountDisclosure();
        const button = wrapper.get('button');
        const controls = button.attributes('aria-controls');

        expect(controls).toBeTruthy();
        expect(wrapper.find(`#${controls}`).exists()).toBe(true);
    });
});
