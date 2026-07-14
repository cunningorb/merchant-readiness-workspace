import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AssessmentResults from '../AssessmentResults.vue';

const catalog = [
    { key: 'business', label: 'Business' },
    { key: 'platform', label: 'Platform' },
];

const result = {
    assessment: {
        overall_score: 72,
        overall_tier: 'Established',
        ranked_sections: {
            platform: { score: 65, tier: 'Developing' },
            business: { score: 80, tier: 'Established' },
        },
    },
    merchant: {
        company_name: 'Cotopaxi',
    },
    recommendations: [],
    report: {
        payload: {
            merchant: {
                company_name: 'Cotopaxi',
                monthly_order_volume: '1,000-10,000',
                sku_count: '500-5,000',
                ecommerce_platform: 'Shopify',
            },
            heroOpportunity: {
                kind: 'monetary',
                type: 'retained_revenue',
                title: 'Retain more revenue through exchanges',
                summary: 'More exchanges can reduce refund-only outcomes.',
                minimum_value: 12000,
                maximum_value: 26000,
                unit: 'usd_per_year',
                confidence: 'medium',
                effort: 'medium',
            },
            supportingMetrics: [
                { key: 'retained_revenue', label: 'Retained revenue potential', value: '$12,000-$26,000 per year', unit: 'usd_per_year', source: 'opportunity' },
            ],
            topRecommendations: [
                {
                    title: 'Offer exchange-first returns',
                    description: 'Give shoppers a clear exchange path before refund-only flows.',
                    priority: 'high',
                    opportunity_type: 'retained_revenue',
                },
            ],
            remainingRecommendations: [],
            calculationExplanations: {
                retained_revenue: { confidence: 'medium' },
            },
            talkingPoints: [
                {
                    title: 'Exchange conversion is the biggest lever',
                    description: 'Retaining orders matters more than small process tweaks.',
                    expected_impact: 'More retained revenue.',
                },
            ],
            peerComparisons: [],
            actionPlan: {
                this_week: ['Review exchange reasons'],
                plan_next: [],
            },
        },
    },
};

describe('AssessmentResults', () => {
    it('renders the value proposition report and not the legacy diagnostic report', () => {
        const wrapper = mount(AssessmentResults, {
            props: {
                result,
                catalog,
                reportUrl: '/reports/token',
            },
        });

        expect(wrapper.text()).toContain("Cotopaxi's returns opportunity report");
        expect(wrapper.text()).toContain('Your estimated opportunity');
        expect(wrapper.text()).toContain('Retained revenue potential');
        expect(wrapper.text()).not.toContain('Score breakdown');
        expect(wrapper.text()).not.toContain('Capability mapping');
    });
});
