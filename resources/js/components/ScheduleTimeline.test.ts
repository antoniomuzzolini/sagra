import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ScheduleTimeline from './ScheduleTimeline.vue';

const areas = [
    {
        id: 1,
        name: 'Cucina',
        family: 'food_service',
        shifts: [
            {
                id: 10,
                starts_at: '2026-07-12T18:00:00+02:00',
                ends_at: '2026-07-12T22:00:00+02:00',
                needed_people: 4,
                assigned_count: 4,
                notes: null,
            },
            {
                id: 11,
                starts_at: '2026-07-13T11:00:00+02:00',
                ends_at: '2026-07-13T15:00:00+02:00',
                needed_people: 5,
                assigned_count: 2,
                notes: 'pranzo',
            },
        ],
    },
];
const phases = [{ type: 'service', starts_on: '2026-07-11', ends_on: '2026-07-14' }];

describe('ScheduleTimeline', () => {
    it('renders one section per day that has shifts', () => {
        const wrapper = mount(ScheduleTimeline, { props: { areas, phases } });
        // Default is "Tutti": every day with shifts is shown.
        expect(wrapper.findAll('section')).toHaveLength(2);
    });

    it('offers a "Tutti" chip plus one per day', () => {
        const wrapper = mount(ScheduleTimeline, { props: { areas, phases } });
        const chips = wrapper.findAll('button');
        expect(chips).toHaveLength(3);
        expect(chips[0].text()).toBe('Tutti');
    });

    it('filters the timeline to a single day when its chip is picked', async () => {
        const wrapper = mount(ScheduleTimeline, { props: { areas, phases } });
        // Chips are [Tutti, day 12, day 13]; pick the second day.
        await wrapper.findAll('button')[2].trigger('click');
        const sections = wrapper.findAll('section');
        expect(sections).toHaveLength(1);
        // Only the 13th's shift ("2/5", "pranzo") remains, not the 12th's "4/4".
        expect(wrapper.text()).toContain('2/5');
        expect(wrapper.text()).not.toContain('4/4');

        // Picking it again clears the filter (back to "Tutti").
        await wrapper.findAll('button')[2].trigger('click');
        expect(wrapper.findAll('section')).toHaveLength(2);
    });

    it('colors bars green when full and amber when understaffed', () => {
        const wrapper = mount(ScheduleTimeline, { props: { areas, phases } });
        // Bars carry a title attribute; the legend swatches do not.
        const bars = wrapper.findAll('[title]');
        const full = bars.find((b) => b.text() === '4/4')!;
        const short = bars.find((b) => b.text() === '2/5')!;
        expect(full.classes()).toContain('bg-green-500');
        expect(short.classes()).toContain('bg-amber-500');
    });

    it('labels a day with its derived phase', () => {
        const wrapper = mount(ScheduleTimeline, { props: { areas, phases } });
        expect(wrapper.text()).toContain('Apertura al pubblico');
    });

    it('shows an empty state without shifts', () => {
        const wrapper = mount(ScheduleTimeline, { props: { areas: [], phases: [] } });
        expect(wrapper.text()).toContain('Nessun turno ancora');
    });
});
