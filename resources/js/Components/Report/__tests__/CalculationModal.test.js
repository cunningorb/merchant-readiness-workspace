import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import CalculationModal from '../CalculationModal.vue';

const explanation = {
    title: 'Retained revenue potential',
    formula_description: 'Annual order volume x estimated return rate x average order value.',
    inputs: {
        order_volume_band: '1,000-10,000',
        fit_sensitive_categories: ['Apparel', 'Footwear'],
        exchanges_offered: false,
    },
    assumptions: {
        assumed_average_order_value: { value: 85, source: 'configured_assumption' },
        estimated_return_rate: { value: 0.17, source: 'configured_assumption' },
        exchange_conversion_lift: { value: { min: 0.08, max: 0.12 }, source: 'configured_assumption' },
    },
    confidence: 'medium',
    formula_version: '1.0',
};

const Harness = defineComponent({
    components: { CalculationModal },
    setup() {
        const open = ref(false);
        return { open, explanation };
    },
    template: `
        <div>
            <button type="button" data-testid="trigger" @click="open = true">See calculation</button>
            <CalculationModal :open="open" :explanation="explanation" @close="open = false" />
        </div>
    `,
});

let wrapper;

function mountHarness() {
    wrapper = mount(Harness, { attachTo: document.body });
    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
    document.body.style.overflow = '';
});

describe('CalculationModal', () => {
    it('is not rendered while closed', () => {
        mountHarness();

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('opens as an accessible dialog with the calculation content', async () => {
        mountHarness();
        await wrapper.get('[data-testid="trigger"]').trigger('click');

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.attributes('aria-modal')).toBe('true');

        const labelledBy = dialog.attributes('aria-labelledby');
        expect(labelledBy).toBeTruthy();
        expect(dialog.find(`#${labelledBy}`).text()).toContain('Retained revenue potential');

        expect(dialog.text()).toContain(explanation.formula_description);
        expect(dialog.text()).toContain('Your answer');
        expect(dialog.text()).toContain('Configured assumption');
        expect(dialog.text()).toContain('Medium confidence');
        expect(dialog.text()).toContain('1.0');
    });

    it('renders non-primitive assumption and input values as readable text, not raw objects', async () => {
        mountHarness();
        await wrapper.get('[data-testid="trigger"]').trigger('click');

        const text = wrapper.get('[role="dialog"]').text();

        // min/max share object -> percent range
        expect(text).toContain('8%–12%');
        // bare fractional share -> percent
        expect(text).toContain('17%');
        // array input -> comma-joined
        expect(text).toContain('Apparel, Footwear');
        // boolean input -> Yes/No
        expect(text).toContain('No');

        expect(text).not.toContain('[object Object]');
        expect(text).not.toMatch(/\{\s*"/);
    });

    it('moves focus into the dialog on open and locks background scroll', async () => {
        mountHarness();
        await wrapper.get('[data-testid="trigger"]').trigger('click');
        await wrapper.vm.$nextTick();

        const dialog = wrapper.get('[role="dialog"]').element;
        expect(dialog.contains(document.activeElement)).toBe(true);
        expect(document.body.style.overflow).toBe('hidden');
    });

    it('closes on Escape, restores focus to the trigger, and unlocks scroll', async () => {
        mountHarness();
        const trigger = wrapper.get('[data-testid="trigger"]');
        trigger.element.focus();
        await trigger.trigger('click');
        await wrapper.vm.$nextTick();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
        expect(document.body.style.overflow).not.toBe('hidden');
    });
});
