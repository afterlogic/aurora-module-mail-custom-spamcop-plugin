<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailCustomSpamCopPlugin;

use Aurora\System\SettingsProperty;
use Aurora\Modules\MailCustomSpamCopPlugin\Enums;

/**
 * @property bool $Disabled
 * @property int $LowerBoundary
 * @property int $UpperBoundary
 * @property Enums\ActionTypes $DefaultAction
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "LowerBoundary" => new SettingsProperty(
                3,
                "int",
                null,
                "",
            ),
            "UpperBoundary" => new SettingsProperty(
                5,
                "int",
                null,
                "",
            ),
            "DefaultAction" => new SettingsProperty(
                Enums\ActionTypes::Spam,
                "int",
                Enums\ActionTypes::class,
                "",
            ),
            "SieveScriptPath" => new SettingsProperty(
                "filter-spamcop.php",
                "string",
                null,
                "",
            ),
        ];
    }
}
