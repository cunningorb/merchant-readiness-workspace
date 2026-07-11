import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import OpportunityHero from '../OpportunityHero.vue';

const monetaryOpportunity = {
    kind: 'monetary',
    type: 'retained_revenue',
    title: 'Recover revenue lost to refunds',
    summary: 'Converting more refunds into exchanges may keep revenue you currently lose.',
    minimum_value: 32000,
    maximum_value: 51000,
    unit: 'usd_per_year',
    confidence: 'medium',
    effort: 'medium',
};

const quantifiedOpportunity = {
    kind: 'quantified',
    type: 'manual_work_savings',
    title: 'Reclaim manual returns processing time',
    summary: 'Automating repetitive returns steps could free up your team.',
    minimum_value: 10,
    maximum_value: 20,
    unit: 'hours_per_week',
    confidence: 'medium',
    effort: 'low',
};

const fallbackOpportunity = {
    kind: 'fallback',
    title: 'Targeted changes are likely to improve your returns process',
    summary: 'Reviewing your returns process is a good next step.',
    confidence: 'low',
};

describe('OpportunityHero', () => {
    it('formats a monetary opportunity as a whole-dollar range with careful language', () => {
        const wrapper = mount(OpportunityHero, {
            props: { opportunity: monetaryOpportunity },
        });

        expect(wrapper.text()).toContain('$32,000–$51,000');
        expect(wrapper.text()).toContain('may');
        expect(wrapper.text()).toContain('annually');
        expect(wrapper.text()).not.toContain('.00');
        expect(wrapper.text()).toContain(monetaryOpportunity.summary);
    });

    it('shows the title and a unit-labelled range for a quantified opportunity', () => {
        const wrapper = mount(OpportunityHero, {
            props: { opportunity: quantifiedOpportunity },
        });

        expect(wrapper.text()).toContain('Reclaim manual returns processing time');
        expect(wrapper.text()).toContain('10–20 hours per week');
    });

    it('renders the fallback variant without any numbers or dollar signs', () => {
        const wrapper = mount(OpportunityHero, {
            props: { opportunity: fallbackOpportunity },
        });

        expect(wrapper.text()).toContain(fallbackOpportunity.title);
        expect(wrapper.text()).not.toContain('$');
        expect(wrapper.text()).not.toMatch(/\d/);
    });

    it.each([
        ['monetary', monetaryOpportunity],
        ['quantified', quantifiedOpportunity],
        ['fallback', fallbackOpportunity],
    ])('never says profit or guarantees (%s variant)', (_kind, opportunity) => {
        const wrapper = mount(OpportunityHero, {
            props: { opportunity },
        });

        expect(wrapper.text().toLowerCase()).not.toContain('profit');
        expect(wrapper.text().toLowerCase()).not.toContain('guarantee');
    });

    it('shows a confidence badge for the estimate', () => {
        const wrapper = mount(OpportunityHero, {
            props: { opportunity: monetaryOpportunity },
        });

        expect(wrapper.text()).toContain('Medium confidence');
    });

    it('shows a See calculation trigger only when a calculation is available', async () => {
        const withCalculation = mount(OpportunityHero, {
            props: { opportunity: monetaryOpportunity, hasCalculation: true },
        });
        const withoutCalculation = mount(OpportunityHero, {
            props: { opportunity: monetaryOpportunity, hasCalculation: false },
        });

        const trigger = withCalculation.findAll('button').find((button) => button.text().includes('See calculation'));
        expect(trigger).toBeTruthy();
        expect(withoutCalculation.text()).not.toContain('See calculation');

        await trigger.trigger('click');
        expect(withCalculation.emitted('see-calculation')).toHaveLength(1);
    });
});
