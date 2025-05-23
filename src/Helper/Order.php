<?php

namespace Vobapay\Payment\Helper;

use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;
use Vobapay\Payment\Core\Enum\OxOrderTransStatus;

class Order
{
    /**
     * @var Order
     */
    protected static $oInstance = null;

    /**
     * @var ContainerInterface
     */
    protected $oContainer;

    /**
     * Cancel current order because it failed i.e. because customer canceled payment
     *
     * @return void
     */
    public function cancelCurrentOrder()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');

        $oOrder = oxNew(CoreOrder::class);
        if ($oOrder->load($sSessChallenge) === true) {
            if ($oOrder->oxorder__oxtransstatus->value != OxOrderTransStatus::OK) {
                $oOrder->cancelOrder();
            }
        }
        Registry::getSession()->deleteVariable('sess_challenge');
    }

    /**
     * Return billing address parameters from Order object
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    public function getBillingAddressParametersFromOrder(CoreOrder $oOrder)
    {
        $aReturn = [
            'address' => [
                'line1' => trim($oOrder->oxorder__oxbillstreet->value . ' ' . $oOrder->oxorder__oxbillstreetnr->value),
                'postal_code' => $oOrder->oxorder__oxbillzip->value,
                'city' => $oOrder->oxorder__oxbillcity->value,
                'country' => $this->getCountryCode($oOrder->oxorder__oxbillcountryid->value)
            ]
        ];
        if (!empty((string)$oOrder->oxorder__oxbillcompany->value)) {
            $aReturn['name'] = $oOrder->oxorder__oxbillcompany->value;
        } else {
            $sTranslatedSalutation = Registry::getLang()->translateString($oOrder->oxorder__oxbillsal->value);
            if (!empty($sTranslatedSalutation)) {
                $aReturn['name'] = $sTranslatedSalutation . ' ';
            }
            $aReturn['name'] .= $oOrder->oxorder__oxbillfname->value . ' ' . $oOrder->oxorder__oxbilllname->value;
        }

        if (!empty((string)$oOrder->oxorder__oxbillstateid->value)) {
            $aReturn['address']['state'] = $this->getRegionTitle($oOrder->oxorder__oxbillstateid->value);
        }

        if (!empty((string)$oOrder->oxorder__oxbillfon->value)) {
            $aReturn['phone'] = $oOrder->oxorder__oxbillfon->value;
        }

        if (!empty((string)$oOrder->oxorder__oxbillemail->value)) {
            $aReturn['email'] = $oOrder->oxorder__oxbillemail->value;
        }

        return $aReturn;
    }

    /**
     * Loads country object and return country iso code
     *
     * @param string $sCountryId
     * @return string
     */
    protected function getCountryCode($sCountryId)
    {
        $oCountry = oxNew('oxcountry');
        $oCountry->load($sCountryId);
        return $oCountry->oxcountry__oxisoalpha2->value;
    }

    /**
     * Convert region id into region title
     *
     * @param string $sRegionId
     * @return string
     */
    protected function getRegionTitle($sRegionId)
    {
        $oState = oxNew('oxState');
        return $oState->getTitleById($sRegionId);
    }

    /**
     * Return the list of OXID order folders
     *
     * @return array
     */
    public function vobapayGetOrderFolders()
    {
        return Registry::getConfig()->getConfigParam('aOrderfolder');
    }

    /**
     * Return shipping address parameters
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    public function getShippingAddressParameters(CoreOrder $oOrder)
    {
        $aReturn = [
            'address' => [
                'line1' => trim($oOrder->oxorder__oxdelstreet->value . ' ' . $oOrder->oxorder__oxdelstreetnr->value),
                'postal_code' => $oOrder->oxorder__oxdelzip->value,
                'city' => $oOrder->oxorder__oxdelcity->value,
                'country' => $this->getCountryCode($oOrder->oxorder__oxdelcountryid->value)
            ]
        ];
        if (!empty((string)$oOrder->oxorder__oxdelcompany->value)) {
            $aReturn['name'] = $oOrder->oxorder__oxdelcompany->value;
        } else {
            $sTranslatedSalutation = Registry::getLang()->translateString($oOrder->oxorder__oxdelsal->value);
            if (!empty($sTranslatedSalutation)) {
                $aReturn['name'] = $sTranslatedSalutation . ' ';
            }
            $aReturn['name'] .= $oOrder->oxorder__oxdelfname->value . ' ' . $oOrder->oxorder__oxdellname->value;
        }

        if (!empty((string)$oOrder->oxorder__oxdelstateid->value)) {
            $aReturn['address']['state'] = $this->getRegionTitle($oOrder->oxorder__oxdelstateid->value);
        }

        if (!empty((string)$oOrder->oxorder__oxdelfon->value)) {
            $aReturn['phone'] = $oOrder->oxorder__oxdelfon->value;
        }

        return $aReturn;
    }

    /**
     * Returns description text with variables being replaced with appropriate values
     *
     * @param CoreOrder $oOrder
     * @return string
     */
    public function getPurpose(CoreOrder $oOrder)
    {
        $sDefaultDescriptionTest = 'OrderNr: {orderNumber}';
        $oPaymentModel = $oOrder->vobapayGetPaymentModel();

        $sDescriptionText = $oPaymentModel->getConfigParam('payment_description');
        if (empty($sDescriptionText)) {
            $sDescriptionText = $sDefaultDescriptionTest;
        }

        $aSubstitutionArray = [
            '{orderId}' => $oOrder->getId(),
            '{orderNumber}' => $oOrder->oxorder__oxordernr->value,
            '{storeName}' => Registry::getConfig()->getActiveShop()->oxshops__oxname->value,
            '{customer.firstname}' => $oOrder->oxorder__oxbillfname->value,
            '{customer.lastname}' => $oOrder->oxorder__oxbilllname->value,
            '{customer.company}' => $oOrder->oxorder__oxbillcompany->value,
        ];

        return str_replace(array_keys($aSubstitutionArray), array_values($aSubstitutionArray), $sDescriptionText);
    }

    /**
     * Returns config value
     *
     * @param string $sVarName
     * @return mixed|false
     */
    public function getShopConfVar($sVarName)
    {
        $moduleConfiguration = $this
            ->getContainer()
            ->get(ModuleConfigurationDaoBridgeInterface::class)
            ->get(Config::PLUGIN_CODE);
        if (!$moduleConfiguration->hasModuleSetting($sVarName)) {
            return false;
        }
        return $moduleConfiguration->getModuleSetting($sVarName)->getValue();
    }

    /**
     * Returns DependencyInjection container
     *
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        if ($this->oContainer === null) {
            $this->oContainer = ContainerFactory::getInstance()->getContainer();
        }
        return $this->oContainer;
    }

    /**
     * Create singleton instance of order helper
     *
     * @return Order
     */
    public static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Format prices to always have 2 decimal places
     *
     * @param double $dPrice
     * @return string
     */
    protected function formatPrice($dPrice)
    {
        return number_format($dPrice, 2, '.', '');
    }

    /**
     * Returns vat value for given brut price
     *
     * @param double $dBrutPrice
     * @param double $dVat
     * @return double
     */
    protected function getVatValue($dBrutPrice, $dVat)
    {
        $oPrice = oxNew(Price::class);
        $oPrice->setBruttoPriceMode();
        $oPrice->setPrice($dBrutPrice);
        $oPrice->setVat($dVat);
        return $oPrice->getVatValue();
    }
}
