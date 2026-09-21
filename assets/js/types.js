// @flow

export type SettingsArea = {|
    /** translation key of the dropdown this area is listed under */
    group: string,
    /** icon of that dropdown */
    icon: string,
    key: string,
    /** whether the area stores anything per locale, derived from its template */
    localized: boolean,
    order: number,
    /** the webspaces the current user may open this area in, resolved server side */
    permittedWebspaces: Array<string>,
    title: string,
    /** the webspaces the area is declared for, `['*']` for all - for diagnostics, not for filtering */
    webspaces: Array<string>,
|};

export type SettingsAreaGroup = {|
    areas: Array<SettingsArea>,
    icon: string,
    /** translation key, or a plain label when the project passed one */
    name: string,
|};
