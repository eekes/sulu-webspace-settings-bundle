// @flow
import settingsAreaStore from '../stores/settingsAreaStore';

const area = (key, overrides = {}) => ({
    group: 'sulu_webspace_settings.settings',
    icon: 'su-cog',
    key,
    localized: false,
    order: 0,
    permittedWebspaces: ['website'],
    title: key,
    webspaces: ['*'],
    ...overrides,
});

beforeEach(() => {
    settingsAreaStore.setAreas([]);
    settingsAreaStore.setActivityEnabled(false);
});

test('reads the areas out of the admin config', () => {
    settingsAreaStore.setAreas([area('social')]);
    settingsAreaStore.setActivityEnabled(true);

    expect(settingsAreaStore.getArea('social')).toBeDefined();
    expect(settingsAreaStore.getArea('nope')).toBeUndefined();
    expect(settingsAreaStore.activityEnabled).toBe(true);
});

/*
 * The server resolves permittedWebspaces per webspace and per area against the current user, so
 * an area the user cannot open must never reach the select - the endpoint would reject it and a
 * select full of dead options is worse than a short one.
 */
test('offers only the areas the user may open in that webspace', () => {
    settingsAreaStore.setAreas([
        area('social', {permittedWebspaces: ['website', 'shop']}),
        area('shop', {permittedWebspaces: ['shop'], webspaces: ['shop']}),
        area('secret', {permittedWebspaces: []}),
    ]);

    expect(settingsAreaStore.getAreasForWebspace('website').map((a) => a.key)).toEqual(['social']);
    expect(settingsAreaStore.getAreasForWebspace('shop').map((a) => a.key)).toEqual(['social', 'shop']);
});

test('offers nothing without a webspace', () => {
    settingsAreaStore.setAreas([area('social')]);

    expect(settingsAreaStore.getAreasForWebspace(undefined)).toEqual([]);
    expect(settingsAreaStore.getAreasForWebspace(null)).toEqual([]);
});

test('makes one dropdown per group, where the first area of that group would have been', () => {
    settingsAreaStore.setAreas([
        area('social', {group: 'marketing', icon: 'su-share'}),
        area('contact'),
        area('newsletter', {group: 'marketing', icon: 'su-envelope'}),
    ]);

    const groups = settingsAreaStore.getAreaGroupsForWebspace('website');

    expect(groups.map((group) => group.name)).toEqual(['marketing', 'sulu_webspace_settings.settings']);
    expect(groups[0].areas.map((a) => a.key)).toEqual(['social', 'newsletter']);
    expect(groups[1].areas.map((a) => a.key)).toEqual(['contact']);
});

/*
 * The dropdown carries a single icon and the areas under it may each declare their own.
 */
test('takes the icon of the first area of a group', () => {
    settingsAreaStore.setAreas([
        area('social', {group: 'marketing', icon: 'su-share'}),
        area('newsletter', {group: 'marketing', icon: 'su-envelope'}),
    ]);

    expect(settingsAreaStore.getAreaGroupsForWebspace('website')[0].icon).toBe('su-share');
});

test('leaves out a group whose areas are all out of reach', () => {
    settingsAreaStore.setAreas([area('shop', {group: 'shop', permittedWebspaces: ['shop']})]);

    expect(settingsAreaStore.getAreaGroupsForWebspace('website')).toEqual([]);
});
