// @flow
import React from 'react';
import {observer} from 'mobx-react';
import {Toolbar} from 'sulu-admin-bundle/components';
import OptionList from 'sulu-admin-bundle/components/Toolbar/OptionList';
import {translate} from 'sulu-admin-bundle/utils';
import isActiveGroup from '../../utils/isActiveGroup';
import settingsToolbarStyles from './settingsToolbar.scss';
import type {SettingsAreaGroup} from '../../types';

type Props = {|
    /** the area the form shows, or nothing while the activity trail replaces it */
    activeAreaKey: ?string,
    activityVisible: boolean,
    groups: Array<SettingsAreaGroup>,
    onActivityClick: ?() => void,
    onAreaChange: (areaKey: string) => void,
|};

/**
 * The toolbar of the settings form, rendered inside the view rather than through
 * `formToolbarActionRegistry`.
 *
 * Sulu's global toolbar sits at the top of the admin, above the navigation: too far from the form
 * for a switch between settings screens, and it gives no control over placement - `views/Form`
 * puts every toolbar action in its left group and keeps the right one for the publish indicator
 * and the locale select. Rendering the `Toolbar` component ourselves is what makes the two
 * `Toolbar.Controls` groups - left and right - available, which is how the component is meant to
 * be used.
 *
 * Each declared group of areas is one dropdown. A dropdown always reads as its group name, never
 * as the area currently open - which is why it is not a `Toolbar.Select`: a select shows its
 * selected option as its label. It is not a `Toolbar.Dropdown` either, which can mark neither the
 * open area nor its group: it passes no value to its option list, and its button only looks active
 * while it is open. So it is assembled from the two parts `Toolbar.Dropdown` is made of - a
 * `Toolbar.Popover` whose class marks the group of the open area, and an `OptionList` that is given
 * the open area as its value and so draws Sulu's own checkmark next to it.
 */
@observer
class SettingsToolbar extends React.Component<Props> {
    handleOptionClick = (option: Object) => {
        option.onClick();
    };

    render() {
        const {activeAreaKey, activityVisible, groups, onActivityClick} = this.props;

        const areaCount = groups.reduce((count, group) => count + group.areas.length, 0);

        if (areaCount < 2 && !onActivityClick) {
            return null;
        }

        // picking an area is also the way back from the activity trail, so with a single area the
        // dropdown still appears while the trail is open - otherwise there is no way out of it
        const showGroups = areaCount > 1 || activityVisible;

        return (
            <div className={settingsToolbarStyles.settingsToolbar}>
                <Toolbar skin="dark">
                    {/*
                        `grow` is load bearing: `Toolbar.Items` renders its list absolutely
                        positioned inside a container that clips its overflow, so without a
                        growing parent the container collapses to zero width and the toolbar
                        renders as an empty bar. Sulu's own toolbar passes it for the same reason.
                    */}
                    <Toolbar.Controls grow={true}>
                        {showGroups &&
                            <Toolbar.Items>
                                {groups.map((group) => (
                                    <Toolbar.Popover
                                        className={isActiveGroup(group, activeAreaKey)
                                            ? `${settingsToolbarStyles.areaGroup} ${settingsToolbarStyles.activeGroup}`
                                            : settingsToolbarStyles.areaGroup}
                                        icon={group.icon}
                                        key={group.name}
                                        label={translate(group.name)}
                                    >
                                        {(onClose) => (
                                            <div className={settingsToolbarStyles.areaOptions}>
                                                <OptionList
                                                    onClose={onClose}
                                                    onOptionClick={this.handleOptionClick}
                                                    options={group.areas.map((area) => ({
                                                        label: translate(area.title),
                                                        onClick: () => this.props.onAreaChange(area.key),
                                                        value: area.key,
                                                    }))}
                                                    // the skin of the toolbar below; Items hands it to
                                                    // the popover, which does not pass it on
                                                    skin="dark"
                                                    value={activeAreaKey}
                                                />
                                            </div>
                                        )}
                                    </Toolbar.Popover>
                                ))}
                            </Toolbar.Items>
                        }
                    </Toolbar.Controls>

                    <Toolbar.Controls>
                        {!!onActivityClick &&
                            <Toolbar.Button
                                icon="su-clock"
                                onClick={onActivityClick}
                            >
                                {translate('sulu_webspace_settings.settings_activity')}
                            </Toolbar.Button>
                        }
                    </Toolbar.Controls>
                </Toolbar>
            </div>
        );
    }
}

export default SettingsToolbar;
