// @flow
import React from 'react';
import {action, computed, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Form} from 'sulu-admin-bundle/views';
import {ResourceStore} from 'sulu-admin-bundle/stores';
import userStore from 'sulu-admin-bundle/stores/userStore/userStore';
import settingsAreaStore from '../../stores/settingsAreaStore';
import SettingsActivityList from './SettingsActivityList';
import SettingsToolbar from './SettingsToolbar';
import webspaceSettingsFormStyles from './webspaceSettingsForm.scss';
import type {ViewProps} from 'sulu-admin-bundle/containers';
import type {IObservableValue} from 'mobx/lib/mobx';

/**
 * Renders the standard Form view for one settings area.
 *
 * The Form view of sulu-admin-bundle insists on a parent ResourceTabs view to hand it a resource
 * store, and the Webspaces tab is a plain Tabs view. So this view builds the store itself: the id
 * of a settings record is its webspace key, and the area travels as a request parameter.
 *
 * The locale select is rendered by passing `locales`, which depends on the area currently being
 * edited - an area whose every field is `multilingual="false"` has nothing to switch between. A
 * statically configured `setLocales()` on the view could not do that.
 *
 * The area select and the activity link are rendered here too, in a `Toolbar` of our own below
 * the webspace tabs - see {@link SettingsToolbar} for why they are not toolbar actions. The
 * activity trail replaces the form in place rather than navigating away, so the webspace tabs
 * stay on screen; see {@link SettingsActivityList}.
 */
@observer
class WebspaceSettingsForm extends React.Component<ViewProps> {
    resourceStore: ResourceStore;
    locale: IObservableValue<?string> = observable.box();
    // a bound router attribute rather than component state, so the activity trail is
    // deep-linkable and the back button returns to the form
    activity: IObservableValue<*> = observable.box();

    @computed get webspaceKey(): string {
        const {webspace} = this.props.router.attributes;

        if (typeof webspace !== 'string') {
            throw new Error('The "webspace" router attribute must be a string!');
        }

        return webspace;
    }

    /**
     * The area the URL asks for, or the first one this webspace has.
     *
     * The fallback is not cosmetic. The default area of the view is static PHP config and cannot
     * be per webspace, and the `area` attribute survives a switch between webspace tabs - so the
     * URL routinely names an area the current webspace does not have, and the endpoint answers
     * that with a 404 where the editor expects a form.
     */
    @computed get areaKey(): string {
        const {area} = this.props.router.attributes;
        const permittedAreas = settingsAreaStore.getAreasForWebspace(this.webspaceKey);

        if (typeof area === 'string' && permittedAreas.some((candidate) => candidate.key === area)) {
            return area;
        }

        if (permittedAreas.length > 0) {
            return permittedAreas[0].key;
        }

        if (typeof area !== 'string') {
            throw new Error('The "area" router attribute must be a string!');
        }

        return area;
    }

    @computed get area() {
        return settingsAreaStore.getArea(this.areaKey);
    }

    @computed.struct get availableLocales(): Array<string> {
        const {webspace} = this.props;

        if (!webspace || !webspace.allLocalizations) {
            return [];
        }

        return webspace.allLocalizations.map((localization) => localization.localization);
    }

    @computed.struct get locales(): Array<string> | typeof undefined {
        return this.area && this.area.localized ? this.availableLocales : undefined;
    }

    /**
     * The same precedence Sulu's own webspace views use: the content locale the editor last
     * worked in, then the default localization of the webspace. Opening Pages in German and
     * Settings in English otherwise reads as a bug.
     */
    @computed get defaultLocale(): ?string {
        const {webspace} = this.props;

        if (this.availableLocales.includes(userStore.contentLocale)) {
            return userStore.contentLocale;
        }

        return this.findDefaultLocale(webspace ? webspace.localizations : undefined)
            ?? this.availableLocales[0];
    }

    findDefaultLocale(localizations: ?Array<Object>): ?string {
        if (!localizations) {
            return undefined;
        }

        for (const localization of localizations) {
            if (localization.default) {
                return localization.locale;
            }

            // a localization can carry country variants as children, and the default may sit there
            const child = this.findDefaultLocale(localization.children);

            if (child) {
                return child;
            }
        }

        return undefined;
    }

    constructor(props: ViewProps) {
        super(props);

        const {router} = this.props;

        // An area without translated fields also carries a locale: its own values are shared across
        // locales, but anything it points at - a page, a media item - still has to be resolved in
        // some language. Bound rather than set, because a standard form view gets that from its
        // parent ResourceTabs and this view replaces it: without the binding the locale never
        // reaches the URL, so it cannot be linked to and is lost on a reload.
        router.bind('locale', this.locale, this.defaultLocale);
        router.bind('activity', this.activity, false);

        this.resourceStore = new ResourceStore(
            'webspace_settings',
            this.webspaceKey,
            {locale: this.locale},
            {area: this.areaKey}
        );
    }

    componentDidMount() {
        const {router} = this.props;

        if (router.attributes.area !== this.areaKey) {
            // the store above already uses the area this webspace has; this only brings the URL
            // in line, so a reload and the back button keep working
            router.redirect(router.route.name, {...router.attributes, area: this.areaKey});
        }
    }

    componentWillUnmount() {
        this.resourceStore.destroy();
    }

    @computed get areaGroups() {
        return settingsAreaStore.getAreaGroupsForWebspace(this.webspaceKey);
    }

    @computed get activityVisible(): boolean {
        // a query parameter arrives as a string
        const activity = this.activity.get();

        return true === activity || 'true' === activity;
    }

    handleAreaChange: (areaKey: string) => void = (areaKey) => {
        const {router} = this.props;

        // picking an area is also how an editor leaves the activity trail
        router.navigate(router.route.name, {...router.attributes, activity: false, area: areaKey});
    };

    handleActivityClick: () => void = action(() => {
        this.activity.set(true);
    });

    render() {
        const {route} = this.props;

        return (
            <div className={webspaceSettingsFormStyles.settingsView}>
                <SettingsToolbar
                    activityVisible={this.activityVisible}
                    groups={this.areaGroups}
                    onActivityClick={settingsAreaStore.activityEnabled ? this.handleActivityClick : undefined}
                    onAreaChange={this.handleAreaChange}
                />
                {this.activityVisible
                    ? <SettingsActivityList
                        resourceId={this.webspaceKey}
                        resourceKey={route.options.resourceKey}
                    />
                    : <Form
                        {...this.props}
                        locales={this.locales}
                        resourceStore={this.resourceStore}
                    />
                }
            </div>
        );
    }
}

export default WebspaceSettingsForm;
