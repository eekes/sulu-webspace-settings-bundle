// @flow

/**
 * The router attributes for switching the settings form to another area - or to the same one,
 * which is also how an editor leaves the activity trail.
 *
 * The locale is passed explicitly. The router leaves it out of the URL while it equals the default
 * of its binding, so the current attributes often have none, and navigating without it makes the
 * router fall back to that default. Sulu's Form view binds the same observable again without a
 * default, so the fallback is undefined: the form loses its locale and opens the dialog that offers
 * to copy the content from another one.
 */
export default function areaNavigationAttributes(
    attributes: {[string]: mixed},
    areaKey: string,
    locale: ?string
): {[string]: mixed} {
    const navigationAttributes = {...attributes, activity: false, area: areaKey};

    if (locale) {
        navigationAttributes.locale = locale;
    }

    return navigationAttributes;
}
