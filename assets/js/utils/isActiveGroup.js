// @flow
import type {SettingsAreaGroup} from '../types';

/**
 * Whether the group holds the area that is open, so its dropdown can read as the current one.
 */
export default function isActiveGroup(group: SettingsAreaGroup, activeAreaKey: ?string): boolean {
    return !!activeAreaKey && group.areas.some((area) => area.key === activeAreaKey);
}
