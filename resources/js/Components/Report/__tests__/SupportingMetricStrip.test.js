import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SupportingMetricStrip from '../SupportingMetricStrip.vue';

const opportunityMetric = (key, label, value) => ({
    key,
    label,
    value,
    unit: 'usd_per_year',
    source: 'opportunity',
});

describe('SupportingMetricStrip', () => {
    it('caps the strip at three metrics', () => {
        const wrapper = mount(SupportingMetricStrip, {
            props: {
                metrics: [
                    opportunityMetric('a', 'Metric A', '$1,000-$2,000 per year'),
                    opportunityMetric('b', 'Metric B', '$3,000-$4,000 per year'),
                    opportunityMetric('c', 'Metric C', '$5,000-$6,000 per year'),
                    opportunityMetric('d', 'Metric D', '$7,000-$8,000 per year'),
                ],
            },
        });

        expect(wrapper.findAll('[data-testid="supporting-metric"]')).toHaveLength(3);
        expect(wrapper.text()).not.toContain('Metric D');
    });

    it('renders the readiness score metric as a compact stat', () => {
        const wrapper = mount(SupportingMetricStrip, {
            props: {
                metrics: [
                    { key: 'overall_score', label: 'Readiness score', value: 72, unit: null, source: 'score' },
                ],
            },
        });

        expect(wrapper.text()).toContain('Readiness score');
        expect(wrapper.text()).toContain('72');
        expect(wrapper.text()).toContain('out of 100');
    });

    it('shows a confidence badge when a metric carries confidence', () => {
        const wrapper = mount(SupportingMetricStrip, {
            props: {
                metrics: [
                    { ...opportunityMetric('a', 'Metric A', '$1,000-$2,000 per year'), confidence: 'medium' },
                ],
            },
        });

        expect(wrapper.text()).toContain('Medium confidence');
    });
});
