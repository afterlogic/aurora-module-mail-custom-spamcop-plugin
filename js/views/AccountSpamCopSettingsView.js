'use strict';

var
	_ = require('underscore'),
	ko = require('knockout'),

	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Ajax = require('%PathToCoreWebclientModule%/js/Ajax.js'),

	Api = require('%PathToCoreWebclientModule%/js/Api.js'),
	ModulesManager = require('%PathToCoreWebclientModule%/js/ModulesManager.js'),
	Screens = require('%PathToCoreWebclientModule%/js/Screens.js'),

	CAbstractSettingsFormView = ModulesManager.run('SettingsWebclient', 'getAbstractSettingsFormViewClass'),

	AccountList = require('modules/MailWebclient/js/AccountList.js'),

	Settings = {
		ServerModuleName: 'MailCustomSpamCopPlugin'
	}
;

/**
 * @constructor
 */ 
function AccountSpamCopSettingsView()
{
	CAbstractSettingsFormView.call(this, '%ModuleName%')

	this.enabled =  ko.observable(false)
	this.bccAction =  ko.observable(null)
	this.lowerSpamScore =  ko.observable(3)
	this.upperSpamScore =  ko.observable(5)
	// this.spamScore = ko.observable('');
	this.domainAllowList = ko.observable('')
	// this.domainBlockList = ko.observableArray([])

	// this.allowListAction =  ko.observable('')
	// this.blockListAction =  ko.observable('')

	this.aActionOptions = [
		// {
		// 	label: TextUtils.i18n('%MODULENAME%/OPTION_ACTION_NOT_DEFINED'),
		// 	value: ''
		// },
		{
			label: TextUtils.i18n('%MODULENAME%/OPTION_ACTION_KEEP'),
			value: ''
		},
		{
			label: TextUtils.i18n('%MODULENAME%/OPTION_ACTION_SPAM'),
			value: 'spam'
		},
		{
			label: TextUtils.i18n('%MODULENAME%/OPTION_ACTION_DELETE'),
			value: 'delete'
		},
	]
		
	this.showDomainAllowList = ko.computed(() => {
		return this.bccAction()?.value === 'delete' || this.bccAction()?.value === 'spam'
	})
}

_.extendOwn(AccountSpamCopSettingsView.prototype, CAbstractSettingsFormView.prototype);

AccountSpamCopSettingsView.prototype.ViewTemplate = '%ModuleName%_AccountSpamCopSettingsView';

AccountSpamCopSettingsView.prototype.onShow = function ()
{
	this.populate()
};

AccountSpamCopSettingsView.prototype.getCurrentValues = function ()
{
	return [
		this.enabled(),
		this.bccAction(),
		this.lowerSpamScore(),
		this.upperSpamScore(),
		this.domainAllowList(),
		// this.domainBlockList()
	]
}

AccountSpamCopSettingsView.prototype.getParametersForSave = function ()
{
	const oAccount = AccountList.getEdited()
	
	let params = {}
	
	if (oAccount) {
		params = {
			'AccountId': oAccount.id(),
			'Enabled': this.enabled(),
			'LowerBoundary': Types.pDouble(this.lowerSpamScore()),
			'UpperBoundary': Types.pDouble(this.upperSpamScore()),
			'BccAction': this.bccAction()?.value ? this.bccAction().value : '',
			'DomailAllowList': this.domainAllowList() !== '' ? this.domainAllowList()?.split('\n') : [],
			// 'DomailBlockList': this.domainBlockList() !== '' ? this.domainBlockList().split('\n') : [],
		}
	}

	return params
}

AccountSpamCopSettingsView.prototype.save = function ()
{
	this.isSaving(true)

	Ajax.send(Settings.ServerModuleName, 'UpdateAccountSettings', this.getParametersForSave(), this.onUpdateSettingsResponse, this)
}

/**
 * @param {Object} oResponse
 * @param {Object} oRequest
 */
AccountSpamCopSettingsView.prototype.onUpdateSettingsResponse = function (oResponse, oRequest)
{
	this.isSaving(false)

	if (oResponse.Result === false) {
		Api.showErrorByCode(oResponse, TextUtils.i18n('COREWEBCLIENT/ERROR_SAVING_SETTINGS_FAILED'))
	} else {
		this.updateSavedState()
		Screens.showReport(TextUtils.i18n('COREWEBCLIENT/REPORT_SETTINGS_UPDATE_SUCCESS'))
	}
}

AccountSpamCopSettingsView.prototype.populate = function()
{
	const oAccount = AccountList.getEdited()

	if (oAccount) {
		Ajax.send(Settings.ServerModuleName, 'GetAccountSettings', {'AccountId': oAccount.id()}, this.onGetSettingsResponse, this)
	}
	
	this.updateSavedState()
}

/**
 * @param {Object} oResponse
 * @param {Object} oRequest
 */
AccountSpamCopSettingsView.prototype.onGetSettingsResponse = function (oResponse, oRequest)
{
	const oResult = oResponse && oResponse.Result

	if (oResult) {
		const BccAction = Types.pString(oResult.BccAction)
		const oAction = this.aActionOptions.find(item => item.value === BccAction)
		if (oAction) {
			this.bccAction(oAction);
		}

		this.enabled(Types.pBool(oResult.Enabled));
		this.lowerSpamScore(Types.pDouble(oResult.LowerBoundary));
		this.upperSpamScore(Types.pDouble(oResult.UpperBoundary));
		this.domainAllowList(Types.pArray(oResult.AllowDomainList).join('\n'));

		this.updateSavedState();
	}
}

module.exports = new AccountSpamCopSettingsView();
