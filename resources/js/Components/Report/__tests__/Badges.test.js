import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ConfidenceBadge from '../ConfidenceBadge.vue';
import EffortBadge from '../EffortBadge.vue';

describe('ConfidenceBadge', () => {
    it.each([
        ['low', 'Low confidence'],
        ['medium', 'Medium confidence'],
        ['high', 'High confidence'],
    ])('renders the %s level as readable text', (level, expected) => {
        const wrapper = mount(ConfidenceBadge, { props: { level } });

        expect(wrapper.text()).toBe(expected);
    });
});

describe('EffortBadge', () => {
    it.each([
        ['low', 'Low effort'],
        ['medium', 'Medium effort'],
        ['high', 'High effort'],
    ])('renders the %s level as readable text', (level, expected) => {
        const wrapper = mount(EffortBadge, { props: { level } });

        expect(wrapper.text()).toBe(expected);
    });
});
