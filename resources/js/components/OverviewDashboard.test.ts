import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import OverviewDashboard, { type OverviewArea } from './OverviewDashboard.vue';

const areas: OverviewArea[] = [
    { id: 1, name: 'Cucina', family: 'food_service', needed: 4, filled: 4, people: ['Anna Ricci', 'Bob Bo'], managers: ['Cap Cap'] },
    { id: 2, name: 'Bar', family: null, needed: 6, filled: 2, people: [], managers: [] },
    { id: 3, name: 'Pulizie', family: null, needed: 0, filled: 0, people: [], managers: [] },
];

const areaButton = (wrapper: ReturnType<typeof mount>, name: string) => wrapper.findAll('button').find((b) => b.text().includes(name))!;

describe('OverviewDashboard', () => {
    it('totals coverage across areas', () => {
        const wrapper = mount(OverviewDashboard, { props: { areas } });
        const text = wrapper.text();
        expect(text).toContain('6 / 10'); // filled / needed
        expect(text).toContain('60%'); // coverage
    });

    it('counts only understaffed areas as needing help', () => {
        const wrapper = mount(OverviewDashboard, { props: { areas } });
        // Only Bar is understaffed (Cucina full, Pulizie has no shifts).
        const daCoprire = wrapper.findAll('.rounded-xl').find((el) => el.text().includes('Da coprire'));
        expect(daCoprire?.text()).toContain('1');
    });

    it('labels each area by its coverage', () => {
        const wrapper = mount(OverviewDashboard, { props: { areas } });
        expect(areaButton(wrapper, 'Cucina').text()).toContain('Completo');
        expect(areaButton(wrapper, 'Bar').text()).toContain('Servono 4');
        expect(areaButton(wrapper, 'Pulizie').text()).toContain('Nessun turno');
    });

    it('shows the managers label or a fallback', () => {
        const wrapper = mount(OverviewDashboard, { props: { areas } });
        expect(areaButton(wrapper, 'Cucina').text()).toContain('Resp. · Cap Cap');
        expect(areaButton(wrapper, 'Bar').text()).toContain('Nessun responsabile');
    });

    it('emits select with the area id when a card is clicked', async () => {
        const wrapper = mount(OverviewDashboard, { props: { areas } });
        await areaButton(wrapper, 'Bar').trigger('click');
        expect(wrapper.emitted('select')?.[0]).toEqual([2]);
    });

    it('emits uncovered when the "Da coprire" stat is clicked', async () => {
        const wrapper = mount(OverviewDashboard, { props: { areas } });
        const daCoprire = wrapper.findAll('button').find((b) => b.text().includes('Da coprire'))!;
        expect(daCoprire).toBeTruthy();
        await daCoprire.trigger('click');
        expect(wrapper.emitted('uncovered')).toHaveLength(1);
    });

    it('does not make "Da coprire" clickable when everything is covered', () => {
        const covered: OverviewArea[] = [{ id: 1, name: 'Cucina', family: null, needed: 2, filled: 2, people: [], managers: [] }];
        const wrapper = mount(OverviewDashboard, { props: { areas: covered } });
        const daCoprire = wrapper.findAll('button').find((b) => b.text().includes('Da coprire'));
        expect(daCoprire).toBeUndefined();
    });

    it('shows the create tile only when allowed and emits create', async () => {
        const withCreate = mount(OverviewDashboard, { props: { areas, canCreate: true } });
        const tile = withCreate.findAll('button').find((b) => b.text().includes('Nuova area'))!;
        expect(tile).toBeTruthy();
        await tile.trigger('click');
        expect(withCreate.emitted('create')).toHaveLength(1);

        const withoutCreate = mount(OverviewDashboard, { props: { areas } });
        expect(withoutCreate.findAll('button').some((b) => b.text().includes('Nuova area'))).toBe(false);
    });
});
