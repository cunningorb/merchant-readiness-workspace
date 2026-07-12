import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import PeerPerspectivePanel from '../PeerPerspectivePanel.vue';

const comparison = (overrides = {}) => ({
    metric_key: 'return_window_days',
    label: 'Return window',
    merchant_value: 30,
    merchant_value_formatted: '30 days',
    minimum_value: 15,
    maximum_value: 45,
    unit: 'days',
    range_formatted: '15–45 days',
    interpretation: 'Your 30-day window sits within the common range.',
    source_type: 'illustrative',
    source_label: 'Illustrative benchmark',
    methodology: 'These are configured, illustrative reference ranges, not measured industry data.',
    benchmark_version: '1.0',
    ...overrides,
});

let wrapper;

function mountPanel(comparisons) {
    wrapper = mount(PeerPerspectivePanel, {
        props: { comparisons },
        attachTo: document.body,
    });

    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
});

describe('PeerPerspectivePanel', () => {
    it('renders nothing when there are no comparisons', () => {
        const wrapper = mountPanel([]);

        expect(wrapper.find('[data-testid="peer-perspective"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Peer perspective');
        expect(wrapper.html()).toBe('<!--v-if-->');
    });

    it('renders the section heading when comparisons are present', () => {
        const wrapper = mountPanel([comparison()]);

        expect(wrapper.find('[data-testid="peer-perspective"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Peer perspective');
    });

    it('shows each comparison merchant value, range, interpretation, source label and source type', () => {
        const wrapper = mountPanel([comparison({ source_label: 'Reference cohort', source_type: 'external_reference' })]);
        const row = wrapper.get('[data-testid="peer-comparison-row"]');

        expect(row.text()).toContain('Return window');
        expect(row.text()).toContain('30 days');
        expect(row.text()).toContain('15–45 days');
        expect(row.text()).toContain('Your 30-day window sits within the common range.');
        expect(row.text()).toContain('Reference cohort');
        expect(row.text()).toContain('External reference');
    });

    it('renders exactly one row per comparison passed', () => {
        const wrapper = mountPanel([
            comparison({ metric_key: 'a', label: 'Return window' }),
            comparison({ metric_key: 'b', label: 'Manual processing hours' }),
            comparison({ metric_key: 'c', label: 'Catalog SKU count' }),
        ]);

        expect(wrapper.findAll('[data-testid="peer-comparison-row"]')).toHaveLength(3);
    });

    it('shows a visible source label on every comparison, never hidden behind a click', () => {
        const wrapper = mountPanel([
            comparison({ metric_key: 'a', source_label: 'Illustrative benchmark' }),
            comparison({ metric_key: 'b', source_label: 'Verified survey' }),
        ]);

        const rows = wrapper.findAll('[data-testid="peer-comparison-row"]');
        expect(rows[0].get('[data-testid="peer-source-label"]').isVisible()).toBe(true);
        expect(rows[0].get('[data-testid="peer-source-label"]').text()).toContain('Illustrative benchmark');
        expect(rows[1].get('[data-testid="peer-source-label"]').text()).toContain('Verified survey');
    });

    it('does not render any chart, canvas or svg-chart element', () => {
        const wrapper = mountPanel([comparison()]);

        expect(wrapper.find('canvas').exists()).toBe(false);
        expect(wrapper.find('[data-testid="radar-chart"]').exists()).toBe(false);
        expect(wrapper.find('.recharts-wrapper').exists()).toBe(false);
    });

    it('includes a single methodology disclosure for the panel', () => {
        const wrapper = mountPanel([comparison(), comparison({ metric_key: 'b' })]);

        const triggers = wrapper.findAll('button').filter((button) => button.text().includes('benchmark'));
        expect(triggers.length).toBeGreaterThanOrEqual(1);
        // Methodology disclosure trigger appears once, not per row.
        expect(wrapper.findAll('[data-testid="benchmark-methodology"]')).toHaveLength(1);
    });
});
