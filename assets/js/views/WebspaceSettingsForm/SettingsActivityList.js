// @flow
import React from 'react';
import {observable} from 'mobx';
import {observer} from 'mobx-react';
import {List, ListStore} from 'sulu-admin-bundle/containers';
import type {IObservableValue} from 'mobx/lib/mobx';

const RESOURCE_KEY = 'activities';
const LIST_KEY = 'activities';
const USER_SETTINGS_KEY = 'sulu_webspace_settings.activity';

type Props = {|
    /** the resource key the activities were logged for */
    resourceKey: string,
    /** the resource id of the log: the webspace key, see the Admin class */
    resourceId: string,
|};

/**
 * The activity trail of one webspace's settings, rendered inside the settings view.
 *
 * It deliberately does not have a route of its own. Every child of a tabs view renders as a tab,
 * and a route outside the tabs view loses the webspace tabs entirely - neither is what an editor
 * wants when they only asked to see what changed. Rendering it here keeps *Pages*, *Analytics*
 * and the rest of the webspace chrome on screen, and the settings toolbar with it.
 *
 * The columns still come from Sulu's `activities` list metadata; only the list options that
 * `ActivityViewBuilderFactory` would have set on a view are repeated here.
 */
@observer
class SettingsActivityList extends React.Component<Props> {
    page: IObservableValue<number> = observable.box(1);
    listStore: ListStore;

    constructor(props: Props) {
        super(props);

        const {resourceId, resourceKey} = this.props;

        this.listStore = new ListStore(
            RESOURCE_KEY,
            LIST_KEY,
            USER_SETTINGS_KEY,
            {page: this.page},
            {resourceKey, resourceId}
        );
    }

    componentWillUnmount() {
        this.listStore.destroy();
    }

    render() {
        return (
            <List
                adapterOptions={{table: {skin: 'flat', show_header: false}}}
                adapters={['table']}
                copyable={false}
                deletable={false}
                filterable={false}
                movable={false}
                orderable={false}
                searchable={false}
                selectable={false}
                showColumnOptions={false}
                store={this.listStore}
            />
        );
    }
}

export default SettingsActivityList;
