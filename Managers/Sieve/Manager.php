<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailCustomSpamCopPlugin\Managers\Sieve;

use Aurora\Api;
use Aurora\Modules\MailCustomSpamCopPlugin\Enums\ActionTypes;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 */
class Manager extends \Aurora\Modules\Mail\Managers\Sieve\Manager
{
    /**
     * @var object
     */
    protected $oModuleSettings;

    /**
     * @param \Aurora\Modules\Mail\Module $oModule
     * @param \Aurora\Modules\MailCustomSpamCopPlugin\Settings $oSettings
     */
    public function __construct(\Aurora\System\Module\AbstractModule $oModule, $oSettings)
    {
        parent::__construct($oModule);

        $this->oModuleSettings = $oSettings;

        array_splice($this->aSectionsOrders, 0, 0, ['SpamCop']);
    }

    /**
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     *
     * @return bool
     */
    public function checkIfRuleExists($oAccount)
    {
        $bResult = false;

        $this->_parseSectionsData($oAccount);
        $sData = $this->_getSectionData('SpamCop');

        $aMatch = array();
        if (!empty($sData) && preg_match('/#data=([^\n]+)/', $sData, $aMatch) && isset($aMatch[1])) {
            $oData = \base64_decode($aMatch[1]);

            if ($oData && $oData === $this->oModuleSettings->SieveScriptPath) {
                $bResult = true;
            }
        }

        return $bResult;
    }

    /**
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @param boolean $bEnabled
     * @param ActionTypes $Action
     *
     * @return bool
     */
    public function setSpamCopRule($oAccount, $bEnable = true, $Action = ActionTypes::Spam)
    {
        $sData = '';

        $bAdded = false;
        $bSaved = false;

        if ($bEnable) {
            $sEncodedData = \base64_encode('filter-spamcop.php');
            $sData .= '#data=' . $sEncodedData . "\n";
            $sData .= "if not execute :pipe \"" . $this->oModuleSettings->SieveScriptPath . "\" {\n";
            $sData .= "    " . ($Action === ActionTypes::Delete ? "discard;" : "fileinto \"Spam\";") . "\n";
            $sData .= "    stop;\n";
            $sData .= "}\n";

            $this->_addRequirement('SpamCop', 'vnd.dovecot.execute');

            $bAdded = true;
        } else {
            Api::Log('"SpamCop" settings has not yet been set.');
        }

        $this->_parseSectionsData($oAccount);
        $this->_setSectionData('SpamCop', $sData);

        if (self::AutoSave) {
            $bSaved = $this->_resaveSectionsData($oAccount);
        }

        return $bSaved && $bAdded === $bEnable;
    }
}
