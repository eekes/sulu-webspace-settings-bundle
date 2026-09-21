// @flow
import {initializer} from 'sulu-admin-bundle/services';
import {viewRegistry} from 'sulu-admin-bundle/containers';
import settingsAreaStore from './stores/settingsAreaStore';
import WebspaceSettingsForm from './views/WebspaceSettingsForm';

initializer.addUpdateConfigHook('sulu_webspace_settings', (config: Object, initialized: boolean) => {
    settingsAreaStore.setAreas(config.areas || []);
    settingsAreaStore.setActivityEnabled(!!config.activity);

    if (initialized) {
        return;
    }

    viewRegistry.add('sulu_webspace_settings.settings_form', WebspaceSettingsForm);
});
