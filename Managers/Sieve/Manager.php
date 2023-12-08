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
     * @return array
     */
    public function getSpamCopRule($oAccount)
    {
        $this->_parseSectionsData($oAccount);
        $sData = $this->_getSectionData('SpamCop');

        // defining defailt values
        $aResult = [
            'Enabled' => false,
            'UpperBoundary' => (float) $this->oModuleSettings->UpperBoundary,
            'LowerBoundary' => (float) $this->oModuleSettings->LowerBoundary,
            'Action' => ActionTypes::Spam,
            'AllowDomainList' => []
        ];

        $aMatch = array();
        if (!empty($sData) && preg_match('/#data=([^\n]+)/', $sData, $aMatch) && isset($aMatch[1])) {
            $oData = \json_decode(\base64_decode($aMatch[1]), true);

            if ($oData) {
                $aResult['Enabled'] = isset($oData['Enabled']) ? $oData['Enabled'] : $aResult['Enabled'];
                $aResult['UpperBoundary'] = isset($oData['UpperBoundary']) ? $oData['UpperBoundary'] : $aResult['UpperBoundary'];
                $aResult['LowerBoundary'] = isset($oData['LowerBoundary']) ? $oData['LowerBoundary'] : $aResult['LowerBoundary'];
                $aResult['Action'] = isset($oData['Action']) ? $oData['Action'] : $aResult['Action'];
                $aResult['AllowDomainList'] = isset($oData['AllowDomainList']) ? $oData['AllowDomainList'] : $aResult['AllowDomainList'];
            }
        }

        return $aResult;
    }

    /**
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @param boolean $bEnabled
     * @param ActionTypes $Action
     *
     * @return bool
     */
    public function setSpamCopRule($oAccount, $bEnable = true, $aData = [])
    {
        $sData = '';

        $bSaved = false;

        $Action = isset($aData['Action']) ? $aData['Action'] : ActionTypes::Spam;

        $sEncodedData = \base64_encode(json_encode($aData));
        $sEncryptedData = '#data=' . $sEncodedData . "\n";
        $sData .= "if not execute :pipe \"" . $this->oModuleSettings->SieveScriptPath . " '" . $sEncodedData . "'\" {\n";
        $sData .= "    " . ($Action === ActionTypes::Delete ? "discard;" : "fileinto \"Spam\";") . "\n";
        $sData .= "    stop;\n";
        $sData .= "}\n";

        $this->_addRequirement('SpamCop', 'vnd.dovecot.execute');

        if (!$bEnable) {
            $sData = '#' . implode("\n#", explode("\n", $sData));
        }

        $sData = $sEncryptedData . $sData;

        $this->_parseSectionsData($oAccount);
        $this->_setSectionData('SpamCop', $sData);

        if (self::AutoSave) {
            $bSaved = $this->_resaveSectionsData($oAccount);
        }

        return $bSaved;
    }
}
