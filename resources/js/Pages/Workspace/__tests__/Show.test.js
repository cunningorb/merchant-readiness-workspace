import { describe, it, expect, afterEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import axios from 'axios';
import Show from '../Show.vue';

vi.mock('axios', () => ({
    default: {
        post: vi.fn(() => Promise.resolve({ data: { status: 'queued' } })),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<span />' },
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

vi.mock('../../../Layouts/AuthenticatedLayout.vue', () => ({
    default: {
        template: '<div><slot name="header" /><slot /></div>',
    },
}));

const report = {
    token: 'workspace-token',
    url: 'https://example.com/reports/workspace-token',
    merchant: {
        company_name: 'Thistle & Bloom Apparel',
        contact_name: 'Priya Anand',
        contact_email: 'hello@thistleandbloom.example',
        website: 'thistleandbloom.example',
    },
    assessment: {
        overall_score: 0,
        overall_tier: 'Foundational',
        section_scores: {
            return_policy: { score: 0, tier: 'Foundational' },
            exchanges: { score: 0, tier: 'Foundational' },
        },
        ranked_sections: {
            return_policy: { score: 0, tier: 'Foundational' },
            exchanges: { score: 0, tier: 'Foundational' },
        },
    },
    recommendations: [],
    submitted_at: '2026-07-12T00:00:00.000000Z',
    heroOpportunity: {
        kind: 'monetary',
        type: 'retained_revenue',
        title: 'Retained revenue from exchange conversion',
        summary: 'Estimated revenue that may be retained per year by converting more eligible returns into exchanges instead of refunds.',
        minimum_value: 105000,
        maximum_value: 210000,
        unit: 'usd_per_year',
        confidence: 'medium',
        effort: 'medium',
        assumptions: {},
        evidence: { inputs: {} },
        formula_version: '1.0',
    },
    supportingMetrics: [
        { key: 'retained_revenue', label: 'Retained revenue potential', value: '$105,000-$210,000 per year', unit: 'usd_per_year', source: 'opportunity' },
        { key: 'manual_work_savings', label: 'Manual work savings', value: '20-35 hours per week', unit: 'hours_per_week', source: 'opportunity' },
        { key: 'overall_score', label: 'Readiness score', value: 0, unit: null, source: 'score' },
    ],
    calculationExplanations: {
        retained_revenue: {
            title: 'Retained revenue potential',
            formula_description: 'Annual order volume x estimated return rate x average order value.',
            inputs: { order_volume_band: '1,000-10,000' },
            assumptions: {},
            confidence: 'medium',
            formula_version: '1.0',
        },
    },
    topRecommendations: [
        {
            title: 'Offer exchanges, not just refunds',
            description: 'Exchange conversion can retain revenue that would otherwise be lost to refunds.',
            category: 'exchanges',
            priority: 'high',
            expected_impact: 'Retain revenue currently lost to refund-only returns.',
            opportunity_type: 'retained_revenue',
        },
    ],
    remainingRecommendations: [],
    peerComparisons: [],
    talking_points: [
        {
            title: 'See how automation and AI can level up your returns',
            description: 'Use this report as a starting point for where automation and AI can improve the returns experience for your customers and your business.',
            expected_impact: 'A clearer path to faster returns workflows, better customer experience, and fewer manual bottlenecks.',
        },
        {
            title: 'Offer exchanges, not just refunds',
            description: 'Exchange conversion can retain revenue that would otherwise be lost to refunds.',
            expected_impact: 'Retain revenue currently lost to refund-only returns.',
        },
    ],
};

const catalog = [
    { key: 'return_policy', label: 'Return Policy' },
    { key: 'exchanges', label: 'Exchanges' },
];

let wrapper;

function mountShow() {
    wrapper = mount(Show, {
        props: { report, catalog },
        global: {
            mocks: {
                route: () => '/dashboard',
            },
        },
        attachTo: document.body,
    });

    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
    document.body.style.overflow = '';
});

describe('Workspace/Show', () => {
    it('leads with the quantified revenue opportunity without the legacy diagnostic breakdown', () => {
        const wrapper = mountShow();
        const html = wrapper.html();

        const heroIndex = html.indexOf('data-testid="opportunity-hero"');

        expect(heroIndex).toBeGreaterThan(-1);
        expect(html).not.toContain('Full diagnostic breakdown');
        expect(wrapper.text()).toContain('$105K - $210K');
        expect(wrapper.text()).toContain('Score breakdown');
        expect(wrapper.text()).toContain('Capability map');
        expect(wrapper.text()).not.toContain('Capability mapping');
    });

    it('removes talking points from the internal report view', () => {
        const wrapper = mountShow();

        expect(wrapper.text()).not.toContain('Talking Points');
        expect(wrapper.text()).not.toContain('See how automation and AI can level up your returns');
        expect(wrapper.text()).toContain('Offer exchanges, not just refunds');
    });

    it('links the report header back to the dashboard', () => {
        const wrapper = mountShow();

        expect(wrapper.get('[data-testid="report-back-link"]').attributes('href')).toBe('/dashboard');
    });

    it('opens the sales contact popup from the hero', async () => {
        const wrapper = mountShow();

        await wrapper.get('[data-testid="sales-contact-link"]').trigger('click');

        expect(wrapper.get('[role="dialog"]').text()).toContain('A sales team member will be contacting them at hello@thistleandbloom.example shortly.');
        expect(axios.post).toHaveBeenCalledWith('/api/reports/workspace-token/contact');
    });

    it('opens a share popup instead of printing from the header share button', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            configurable: true,
        });
        window.print = vi.fn();
        const wrapper = mountShow();

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Share')
            .trigger('click');

        expect(window.print).not.toHaveBeenCalled();
        expect(wrapper.get('[aria-label="Share report"]').text()).toContain('https://example.com/reports/workspace-token');

        await wrapper.get('[data-testid="copy-share-link"]').trigger('click');

        expect(writeText).toHaveBeenCalledWith('https://example.com/reports/workspace-token');
    });
});
