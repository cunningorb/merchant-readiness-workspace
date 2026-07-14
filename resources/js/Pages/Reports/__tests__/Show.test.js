import { describe, it, expect, afterEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import axios from 'axios';
import Show from '../Show.vue';

vi.mock('axios', () => ({
    default: {
        post: vi.fn(() => Promise.resolve({ data: { status: 'queued' } })),
    },
}));

const recommendation = (title, category, priority, opportunityType = null) => ({
    title,
    description: `${title} description sentence.`,
    category,
    priority,
    expected_impact: 'Meaningful improvement',
    opportunity_type: opportunityType,
});

const report = {
    token: 'report-token',
    url: 'https://example.com/reports/report-token',
    merchant: {
        company_name: 'Northwind Supply',
        contact_email: 'ops@northwind.test',
        monthly_order_volume: '1,000-10,000',
        ecommerce_platform: 'Shopify',
    },
    assessment: {
        overall_score: 72,
        overall_tier: 'Established',
        section_scores: {
            return_policy: { score: 60, tier: 'Developing' },
            exchanges: { score: 90, tier: 'Advanced' },
        },
        ranked_sections: {
            return_policy: { score: 60, tier: 'Developing' },
            exchanges: { score: 90, tier: 'Advanced' },
        },
    },
    recommendations: [],
    published_at: '2026-07-01T00:00:00.000000Z',
    heroOpportunity: {
        kind: 'monetary',
        type: 'retained_revenue',
        title: 'Recover revenue lost to refunds',
        summary: 'Converting more refunds into exchanges may keep revenue you currently lose.',
        minimum_value: 32000,
        maximum_value: 51000,
        unit: 'usd_per_year',
        confidence: 'medium',
        effort: 'medium',
        assumptions: {},
        evidence: { inputs: {} },
        formula_version: '1.0',
    },
    supportingMetrics: [
        { key: 'retained_revenue', label: 'Retained revenue potential', value: '$32,000-$51,000 per year', unit: 'usd_per_year', source: 'opportunity' },
        { key: 'manual_work_savings', label: 'Manual work savings', value: '10-20 hours per week', unit: 'hours_per_week', source: 'opportunity' },
        { key: 'overall_score', label: 'Readiness score', value: 72, unit: null, source: 'score' },
    ],
    topRecommendations: [
        recommendation('Enable instant exchanges', 'exchanges', 'high', 'retained_revenue'),
        recommendation('Automate return label generation', 'manual_operations', 'high', 'manual_work_savings'),
        recommendation('Clarify your return policy', 'return_policy', 'medium', null),
    ],
    remainingRecommendations: [
        recommendation('Publish a returns FAQ', 'return_policy', 'medium'),
        recommendation('Audit carrier performance', 'platform', 'low'),
    ],
    calculationExplanations: {
        retained_revenue: {
            title: 'Retained revenue potential',
            formula_description: 'Annual order volume x estimated return rate x average order value.',
            inputs: { order_volume_band: '1,000-10,000' },
            assumptions: {
                assumed_average_order_value: { value: 85, source: 'configured_assumption' },
            },
            confidence: 'medium',
            formula_version: '1.0',
        },
        manual_work_savings: {
            title: 'Manual work savings',
            formula_description: 'Weekly manual returns hours x automatable share.',
            inputs: { weekly_hours_band: '10-20' },
            assumptions: {},
            confidence: 'medium',
            formula_version: '1.0',
        },
    },
    actionPlan: {
        this_week: ['Enable instant exchanges', 'Automate return label generation'],
        plan_next: ['Clarify your return policy', 'Publish a returns FAQ'],
    },
    peerComparisons: [
        {
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
        },
    ],
    talkingPoints: [
        {
            title: 'See how automation and AI can level up your returns',
            description: 'Use this report as a starting point for where automation and AI can improve the returns experience for your customers and your business.',
            expected_impact: 'A clearer path to faster returns workflows, better customer experience, and fewer manual bottlenecks.',
        },
    ],
};

const catalog = [
    { key: 'return_policy', label: 'Return Policy' },
    { key: 'exchanges', label: 'Exchanges' },
];

let wrapper;

// attachTo is required so happy-dom computes v-show's display style for isVisible().
function mountShow() {
    wrapper = mount(Show, {
        props: { report, catalog },
        attachTo: document.body,
    });

    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
    document.body.style.overflow = '';
});

describe('Reports/Show', () => {
    it('orders the compact report sections according to the issue plan', () => {
        const wrapper = mountShow();
        const html = wrapper.html();

        const heroIndex = html.indexOf('data-testid="opportunity-hero"');
        const summaryIndex = html.indexOf('Executive summary');
        const scoreIndex = html.indexOf('Score breakdown');
        const opportunitiesIndex = html.indexOf('Top opportunities');
        const capabilityIndex = html.indexOf('Capability map');

        expect(heroIndex).toBeGreaterThan(-1);
        expect(summaryIndex).toBeGreaterThan(heroIndex);
        expect(scoreIndex).toBeGreaterThan(summaryIndex);
        expect(opportunitiesIndex).toBeGreaterThan(scoreIndex);
        expect(capabilityIndex).toBeGreaterThan(opportunitiesIndex);
    });

    it('shows the hero opportunity headline range', () => {
        const wrapper = mountShow();

        expect(wrapper.text()).toContain('$32K - $51K');
    });

    it('shows only three recommendations until the disclosure is expanded', async () => {
        const wrapper = mountShow();

        const visibleCards = () =>
            wrapper.findAll('[data-testid="recommendation-card"]').filter((card) => card.isVisible());

        expect(visibleCards()).toHaveLength(3);
        expect(wrapper.text()).toContain('View all 2 recommendations');

        const toggle = wrapper
            .findAll('button')
            .find((button) => button.text().includes('View all 2 recommendations'));
        await toggle.trigger('click');

        expect(visibleCards()).toHaveLength(5);
    });

    it('renders condensed recommended improvements instead of the old action plan lists', () => {
        const wrapper = mountShow();

        expect(wrapper.text()).toContain('Recommended improvements');
        expect(wrapper.text()).toContain('Enable instant exchanges');
        expect(wrapper.text()).toContain('Publish a returns FAQ');
        expect(wrapper.text()).not.toContain('Do this this week');
        expect(wrapper.text()).not.toContain('Plan next');
    });

    it('removes talking points and opens the sales contact popup from the hero', async () => {
        const wrapper = mountShow();

        expect(wrapper.text()).not.toContain('Talking points');
        expect(wrapper.text()).not.toContain('See how automation and AI can level up your returns');

        await wrapper.get('[data-testid="sales-contact-link"]').trigger('click');

        expect(wrapper.get('[role="dialog"]').text()).toContain('A sales team member will be contacting you at ops@northwind.test shortly.');
        expect(axios.post).toHaveBeenCalledWith('/api/reports/report-token/contact');
    });

    it('renders the new score breakdown and capability map without the legacy diagnostic wrapper', () => {
        const wrapper = mountShow();
        const html = wrapper.html();

        const heroIndex = html.indexOf('data-testid="opportunity-hero"');

        expect(heroIndex).toBeGreaterThan(-1);
        expect(html).not.toContain('Full diagnostic breakdown');
        expect(wrapper.text()).toContain('Score breakdown');
        expect(wrapper.text()).toContain('Capability map');
        expect(wrapper.text()).not.toContain('Capability mapping');
    });

    it('folds peer perspective into the executive summary and removes standalone peer panel', () => {
        const wrapper = mountShow();

        expect(wrapper.find('[data-testid="peer-perspective"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Top 28%');
        expect(wrapper.text()).toContain('of Shopify peers');
        expect(wrapper.text()).not.toContain('Peer perspective');
    });

    it('omits the peer perspective section entirely when there are no comparisons', () => {
        wrapper = mount(Show, {
            props: { report: { ...report, peerComparisons: [] }, catalog },
            attachTo: document.body,
        });

        expect(wrapper.find('[data-testid="peer-perspective"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Peer perspective');
    });

    it('renders full-size contact buttons in the header hero and primary card', () => {
        const wrapper = mountShow();

        expect(wrapper.get('[data-testid="header-contact-sales"]').classes()).toContain('px-5');
        expect(wrapper.get('[data-testid="sales-contact-link"]').classes()).toContain('px-5');
        expect(wrapper.get('[data-testid="primary-card-contact-sales"]').classes()).toContain('px-5');
        expect(wrapper.get('[data-testid="sales-contact-link"]').classes()).not.toContain('w-full');
        expect(wrapper.get('[data-testid="primary-card-contact-sales"]').classes()).not.toContain('w-full');
        expect(wrapper.get('[data-testid="primary-card-contact-sales"]').element.parentElement.className).toContain('md:w-[240px]');
    });

    it('links the report header back to the public home page', () => {
        const wrapper = mountShow();

        expect(wrapper.get('[data-testid="report-back-link"]').attributes('href')).toBe('/');
    });

    it('opens a share popup and copies the report link', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            configurable: true,
        });
        const wrapper = mountShow();

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Share')
            .trigger('click');

        expect(wrapper.get('[aria-label="Share report"]').text()).toContain('https://example.com/reports/report-token');

        await wrapper.get('[data-testid="copy-share-link"]').trigger('click');

        expect(writeText).toHaveBeenCalledWith('https://example.com/reports/report-token');
        expect(wrapper.get('[data-testid="copy-share-link"]').text()).toContain('Copied link');
    });

    it('falls back to document copy when clipboard api is unavailable', async () => {
        Object.defineProperty(navigator, 'clipboard', {
            value: undefined,
            configurable: true,
        });
        document.execCommand = vi.fn(() => true);
        const wrapper = mountShow();

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Share')
            .trigger('click');
        await wrapper.get('[data-testid="copy-share-link"]').trigger('click');

        expect(document.execCommand).toHaveBeenCalledWith('copy');
        expect(wrapper.get('[data-testid="copy-share-link"]').text()).toContain('Copied link');
    });

    it('opens the calculation modal from a See calculation trigger and shows the matching explanation', async () => {
        const wrapper = mountShow();

        const trigger = wrapper
            .findAll('button')
            .find((button) => button.text().includes('See calculation'));
        expect(trigger).toBeTruthy();

        await trigger.trigger('click');

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.text()).toContain('Annual order volume x estimated return rate x average order value.');
    });
});
