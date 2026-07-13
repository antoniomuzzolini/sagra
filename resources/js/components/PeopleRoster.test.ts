import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PeopleRoster, { type PersonRosterRow } from './PeopleRoster.vue';

const people: PersonRosterRow[] = [
    {
        id: 1,
        name: 'Anna Ricci',
        phone: '123',
        email: null,
        role: 'manager',
        areas: ['Cucina'],
        shiftsCount: 2,
        hasLink: true,
        linkLastUsedAt: '2026-07-01T10:00:00Z',
        linkRequested: false,
    },
    {
        id: 2,
        name: 'Bruno Lazzi',
        phone: null,
        email: 'b@x.it',
        role: 'volunteer',
        areas: ['Stage'],
        shiftsCount: 0,
        hasLink: true,
        linkLastUsedAt: null,
        linkRequested: false,
    },
    {
        id: 3,
        name: 'Carla Neri',
        phone: null,
        email: null,
        role: 'volunteer',
        areas: [],
        shiftsCount: 0,
        hasLink: false,
        linkLastUsedAt: null,
        linkRequested: false,
    },
    {
        id: 4,
        name: 'Dario Re',
        phone: '999',
        email: null,
        role: 'volunteer',
        areas: ['Cucina'],
        shiftsCount: 1,
        hasLink: true,
        linkLastUsedAt: 'x',
        linkRequested: true,
    },
];

describe('PeopleRoster', () => {
    it('derives the access status from the link signals', () => {
        const text = mount(PeopleRoster, { props: { people } }).text();
        expect(text).toContain('Attivo'); // Anna: link used
        expect(text).toContain('Invitato'); // Bruno: link never used
        expect(text).toContain('Da invitare'); // Carla: no link
        expect(text).toContain('Da ricontattare'); // Dario: asked for a new link
    });

    it('shows role badges', () => {
        const text = mount(PeopleRoster, { props: { people } }).text();
        expect(text).toContain('Responsabile');
        expect(text).toContain('Volontario');
    });

    it('filters by the search box', async () => {
        const wrapper = mount(PeopleRoster, { props: { people } });
        await wrapper.find('input').setValue('anna');
        expect(wrapper.text()).toContain('Anna Ricci');
        expect(wrapper.text()).not.toContain('Bruno Lazzi');
    });

    it('filters by area', async () => {
        const wrapper = mount(PeopleRoster, { props: { people } });
        await wrapper.find('select').setValue('Stage');
        expect(wrapper.text()).toContain('Bruno Lazzi');
        expect(wrapper.text()).not.toContain('Anna Ricci');
    });

    it('shows an empty message when nothing matches', async () => {
        const wrapper = mount(PeopleRoster, { props: { people } });
        await wrapper.find('input').setValue('zzznobody');
        expect(wrapper.text()).toContain('Nessuna persona trovata');
    });

    it('renders the actions column only when the slot is provided', () => {
        const withActions = mount(PeopleRoster, {
            props: { people },
            slots: { actions: '<button class="act">go</button>' },
        });
        expect(withActions.text()).toContain('Azioni');
        expect(withActions.findAll('button.act')).toHaveLength(people.length);

        const withoutActions = mount(PeopleRoster, { props: { people } });
        expect(withoutActions.text()).not.toContain('Azioni');
    });
});
