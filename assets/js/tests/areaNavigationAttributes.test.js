// @flow
import areaNavigationAttributes from '../utils/areaNavigationAttributes';

test('Keeps the router attributes and switches the area', () => {
    expect(areaNavigationAttributes({webspace: 'website', area: 'social'}, 'contact', 'en')).toEqual({
        activity: false,
        area: 'contact',
        locale: 'en',
        webspace: 'website',
    });
});

test('Carries the locale the form is showing, not the one the router attributes remember', () => {
    // The URL leaves the locale out while it equals the default, so the router attributes often
    // have none. Navigating without it lets the router fall back to the default of the binding -
    // and Sulu's Form view binds the same observable again without a default, so that fallback is
    // undefined: the form loses its locale and offers to copy the content from another one.
    expect(areaNavigationAttributes({webspace: 'website', area: 'social'}, 'social', 'de').locale).toBe('de');
    expect(areaNavigationAttributes({webspace: 'website', locale: 'en'}, 'social', 'de').locale).toBe('de');
});

test('Leaves the activity trail', () => {
    expect(areaNavigationAttributes({webspace: 'website', activity: 'true'}, 'social', 'en').activity).toBe(false);
});

test('Leaves the locale out for an area without one', () => {
    expect(areaNavigationAttributes({webspace: 'website'}, 'social', undefined)).not.toHaveProperty('locale');
});
