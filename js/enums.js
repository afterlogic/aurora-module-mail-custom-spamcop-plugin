'use strict';

const
	_ = require('underscore'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Enums = {}
;

// /**
//  * @enum {number}
//  */
// Enums.SharedAddressbookAccess = {
// 	'NoAccess': 0,
// 	'Write': 1,
// 	'Read': 2
// };

if (typeof window.Enums === 'undefined')
{
	window.Enums = {};
}

_.extendOwn(window.Enums, Enums);

module.exports = {
	init(appData, serverModuleName) {
		const appDataSection = appData[serverModuleName];
		window.Enums.SpamCopActionTypes = Types.pObject(appDataSection && appDataSection.EActionTypes);
	}
};
