import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

describe('vitest smoke', () => {
    it('mounts a trivial component', () => {
        const Comp = defineComponent({ render: () => h('p', 'hello') });
        expect(mount(Comp).text()).toBe('hello');
    });
});
