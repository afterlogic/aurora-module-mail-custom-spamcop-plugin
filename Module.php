<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailCustomSpamCopPlugin;

use Aurora\System\Api;
use Aurora\System\Enums\UserRole;
// use Aurora\System\Enums\UserRole;

use Aurora\Modules\Mail\Module as MailModule;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    protected $aRequireModules = ['Mail'];

    /*
     * @var $oSieveManager Managers\Sieve\Manager
     */
    protected $oSieveManager = null;

    /**
     * Initializes MailCustomSpamCopPlugin Module.
     *
     * @ignore
     */
    public function init()
    {
        //TODO: Subscribe on Mail::CreateAccount to add sieve rules to new account
    }

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     *@return Managers\Sieve\Manager
     */
    protected function getSieveManager()
    {
        if ($this->oSieveManager === null) {
            $this->oSieveManager = new Managers\Sieve\Manager(MailModule::getInstance(), $this->oModuleSettings);
        }

        return $this->oSieveManager;
    }

    public function GetSettings()
    {
        return [
            'EActionTypes' => (new Enums\ActionTypes())->getMap(),
        ];
    }

    public function GetAccountSettings($AccountId)
    {
        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $oUser = Api::getAuthenticatedUser();
        if ($oUser) {
            $oAccount = MailModule::Decorator()->GetAccount($AccountId);

            if ($oAccount) {
                $aData = $this->getSieveManager()->getSpamCopRule($oAccount);
                return [
                    'EActionTypes' => (new Enums\ActionTypes())->getMap(),
                    'Enabled' => $aData['Enabled'],
                    'Action' => $aData['Action'],
                    'UpperBoundary' => $aData['UpperBoundary'],
                    'LowerBoundary' => $aData['LowerBoundary'],
                    'AllowDomainList' => $aData['AllowDomainList']
                ];
            }
        }

        return [];
    }

    public function UpdateAccountSettings($AccountId, $Enabled, $Action, $DomailAllowList, $UpperBoundary, $LowerBoundary)
    {
        $result = false;

        Api::checkUserRoleIsAtLeast(UserRole::NormalUser);

        $oUser = Api::getAuthenticatedUser();
        if ($oUser) {
            $oAccount = MailModule::Decorator()->GetAccount($AccountId);

            $Action = Enums\ActionTypes::validateValue($Action) ? $Action : Enums\ActionTypes::Spam;

            if ($oAccount) {
                $aData = [
                    'Enabled' => (bool) $Enabled,
                    'UpperBoundary' => (float) $UpperBoundary,
                    'LowerBoundary' => (float) $LowerBoundary,
                    'Action' => $Action,
                    'AllowDomainList' => (array) $DomailAllowList
                ];

                $result = $this->getSieveManager()->setSpamCopRule($oAccount, !!$Enabled, $aData);
            }
        }

        return $result;
    }
}
