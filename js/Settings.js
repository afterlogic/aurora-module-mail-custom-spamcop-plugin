'use strict';

const
	_ = require('underscore'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js')
;

module.exports = {
	EActionTypes: {},
	ServerModuleName: '%ModuleName%',

	/**
	 * Initializes settings from AppData object sections.
	 * 
	 * @param {Object} appData Object contained modules settings.
	 */
	init: function (appData)
	{
		const appDataSection = appData['%ModuleName%']

		if (!_.isEmpty(appDataSection)) {
			this.EActionTypes = Types.pObject(appDataSection.EActionTypes, this.EActionTypes)
		}
	},

// 	/**
// 	 * Updates new settings values after saving on server.
// 	 * 
// 	 * @param {integer} numberOfSendersToDisplay
// 	 * @param {string} searchPeriod
// 	 * @param {string} searchFolders
// 	 */
// 	update: function (numberOfSendersToDisplay, searchPeriod, searchFolders)
// 	{
// 		this.NumberOfSendersToDisplay = numberOfSendersToDisplay;
// 		this.SearchPeriod = searchPeriod;
// 		this.searchFolders(searchFolders);
// 	}
}
