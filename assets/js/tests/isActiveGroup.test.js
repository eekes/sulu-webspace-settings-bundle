// @flow
import isActiveGroup from '../utils/isActiveGroup';

const group = (name, areaKeys) => ({
    areas: areaKeys.map((key) => ({
        group: name,
        icon: 'su-cog',
        key,
        localized: false,
        order: 0,
        permittedWebspaces: ['website'],
        title: key,
        webspaces: ['*'],
    })),
    icon: 'su-cog',
    name,
});

test('A group is active while one of its areas is open', () => {
    expect(isActiveGroup(group('marketing', ['social', 'newsletter']), 'newsletter')).toBe(true);
    expect(isActiveGroup(group('shop', ['shop']), 'newsletter')).toBe(false);
});

test('No group is active without an open area, as while the activity trail is shown', () => {
    expect(isActiveGroup(group('marketing', ['social']), null)).toBe(false);
});
