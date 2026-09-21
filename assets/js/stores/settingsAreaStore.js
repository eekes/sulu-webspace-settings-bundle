// @flow
import {action, observable} from 'mobx';
import type {SettingsArea, SettingsAreaGroup} from '../types';

class SettingsAreaStore {
    @observable areas: Array<SettingsArea> = [];
    /** whether the current user may see the activity trail at all */
    @observable activityEnabled: boolean = false;

    @action setAreas(areas: Array<SettingsArea>) {
        this.areas = areas;
    }

    @action setActivityEnabled(activityEnabled: boolean) {
        this.activityEnabled = activityEnabled;
    }

    getArea(key: string): ?SettingsArea {
        return this.areas.find((area) => area.key === key);
    }

    /**
     * Filtering happens on `permittedWebspaces`, which the admin config resolves per webspace and
     * per area against the current user. An area the user cannot open is never offered - the
     * server would reject it anyway, and a select full of dead options is worse than a short one.
     */
    getAreasForWebspace(webspaceKey: ?string): Array<SettingsArea> {
        if (!webspaceKey) {
            return [];
        }

        return this.areas.filter((area) => area.permittedWebspaces.includes(webspaceKey));
    }

    /**
     * The areas of one webspace as one entry per unique group, in the order the areas already
     * have - so a group appears where its first area would have appeared.
     *
     * The icon is the one of that first area: the dropdown carries a single icon and the areas
     * under it may each declare their own, so the rest are ignored. Declaring the same icon on
     * every area of a group is the readable way to write it.
     */
    getAreaGroupsForWebspace(webspaceKey: ?string): Array<SettingsAreaGroup> {
        const groups = [];

        this.getAreasForWebspace(webspaceKey).forEach((area) => {
            const group = groups.find((candidate) => candidate.name === area.group);

            if (group) {
                group.areas.push(area);

                return;
            }

            groups.push({areas: [area], icon: area.icon, name: area.group});
        });

        return groups;
    }
}

export default new SettingsAreaStore();
