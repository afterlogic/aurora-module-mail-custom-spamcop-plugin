'use strict';

module.exports = function (appData) {
	const
		ko = require('knockout'),
		App = require('%PathToCoreWebclientModule%/js/App.js'),

		TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
		Settings = require('modules/%ModuleName%/js/Settings.js')
	;

	Settings.init(appData);

	if (!App.isUserNormalOrTenant()) {
		return null;
	}

	return {
		start: function (ModulesManager) {
			if (!ModulesManager.isModuleEnabled('MailWebclient')) {
				return;
			}

			const AccountSpamCopSettingsView = require('modules/%ModuleName%/js/views/AccountSpamCopSettingsView.js')
			App.subscribeEvent('MailWebclient::ConstructView::after', function (oParams) {
				if ('CAccountsSettingsPaneView' === oParams.Name)
				{
					const AccountSettingsView = oParams.View;

					const showSpamCop = ko.observable(true);
					AccountSettingsView.aAccountTabs.push({
						name: 'spam-cop',
						title: TextUtils.i18n('%MODULENAME%/LABEL_ACCOUNT_SPAM_COP_TAB'),
						view: AccountSpamCopSettingsView,
						visible: showSpamCop
					})
					AccountSettingsView.editedIdentity.valueHasMutated();
				}
			});
		}
	};
};
