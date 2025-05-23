<?php

namespace Vobapay\Payment\Model\Payment;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Application\Model\Order;
use Vobapay\Payment\Core\Enum\CaptureTypes;
use Vobapay\Payment\Model\PaymentConfig;
use Vobapay\Payment\Helper\Config;
use Vobapay\Payment\Helper\Payment;

abstract class Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = null;

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sVobapayPaymentCode = null;

    /**
     * Loaded payment config
     *
     * @var array
     */
    protected $aPaymentConfig = null;

    /**
     * Capture code used for API request
     *
     * @var string
     */
    protected $sVobapayPaymentCapture = CaptureTypes::AUTO;

    /**
     * Determines custom config template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomConfigTemplate = false;

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = false;

    /**
     * Determines if the payment method is hidden at first when payment list is displayed
     *
     * @var bool
     */
    protected $blIsMethodHiddenInitially = false;

    /**
     * Array with currency-codes the payment method is restricted to
     * If property is set to false it is available to all currencies
     *
     * @var array|false
     */
    protected $aCurrencyRestrictedTo = false;

    /**
     * Determines if the payment method is only available for B2B orders
     * B2B mode is assumed when the company field in the billing address is filled
     *
     * @var bool
     */
    protected $blIsOnlyB2BSupported = false;

    /**
     * Return vobapay payment code
     *
     * @return string
     */
    public function getVobapayPaymentCode()
    {
        return $this->sVobapayPaymentCode;
    }

    /**
     * Return vobapay payment code
     *
     * @return string
     */
    public function getVobapayPaymentCapture()
    {
        return $this->sVobapayPaymentCapture;
    }

    /**
     * Returns custom config template or false if not existing
     *
     * @return bool|string
     */
    public function getCustomConfigTemplate()
    {
        if (!empty($this->sCustomConfigTemplate)) {
            return "@" . Config::PLUGIN_CODE . "/customConfigTemplate/" . $this->sCustomConfigTemplate . ".html.twig";
        }
        return false;
    }

    /**
     * Returns custom frontend template or false if not existing
     *
     * @return bool|string
     */
    public function getCustomFrontendTemplate()
    {
        if (!empty($this->sCustomFrontendTemplate)) {
            return "@" . Config::PLUGIN_CODE . "/customFrontendTemplate/" . $this->sCustomFrontendTemplate . ".html.twig";
        }
        return false;
    }

    /**
     * Checks if given basket brutto price is withing the payment sum limitations of the current vobapay payment type
     *
     * @param double $dBasketBruttoPrice
     * @return bool
     */
    public function vobapayIsBasketSumInLimits($dBasketBruttoPrice)
    {
        $oFrom = $this->getVobapayFromAmount();
        if ($oFrom && $dBasketBruttoPrice < $oFrom->value) {
            return false;
        }

        $oTo = $this->getVobapayToAmount();
        if ($oTo && $dBasketBruttoPrice > $oTo->value) {
            return false;
        }
        return true;
    }

    /**
     * Returnes minimum order sum for vobapay payment type to be usable
     *
     * @return object|false
     */
    public function getVobapayFromAmount()
    {
        $aInfo = Payment::getInstance()->getVobapayPaymentInfo();
        if (isset($aInfo[$this->sVobapayPaymentCode]['minAmount'])) {
            return $aInfo[$this->vsobapayPaymentCode]['minAmount'];
        }
        return false;
    }

    /**
     * Returnes maximum order sum for vobapay payment type to be usable
     *
     * @return object|false
     */
    public function getVobapayToAmount()
    {
        $aInfo = Payment::getInstance()->getVobapayPaymentInfo();
        if (!empty(isset($aInfo[$this->sVobapayPaymentCode]['maxAmount']))) {
            return $aInfo[$this->sVobapayPaymentCode]['maxAmount'];
        }
        return false;
    }

    /**
     * Checks if the payment method is available for the current currency
     *
     * @param string $sCurrencyCode
     * @return bool
     */
    public function vobapayIsMethodAvailableForCurrency($sCurrencyCode)
    {
        $aCurrencyRestrictions = $this->getCurrencyRestrictedCurrencies();
        return ($aCurrencyRestrictions === false || in_array($sCurrencyCode, $aCurrencyRestrictions) === true);
    }

    /**
     * Returns array of currency restrictions
     *
     * @return bool
     */
    public function getCurrencyRestrictedCurrencies()
    {
        return $this->aCurrencyRestrictedTo;
    }

    /**
     * Return PaymentMethod parameters specific to the given payment type, if existing
     *
     * @param array $aOrder
     * @param string $sPurpose
     * @return array
     */
    public function getPaymentMethodParams(array $aOrder, $sPurpose)
    {
        return [];
    }

    /**
     * Return the transaction status handler
     *
     * @return \Vobapay\Payment\Model\TransactionHandler\Base
     */
    public function getTransactionHandler()
    {
        return new \Vobapay\Payment\Model\TransactionHandler\Payment();
    }

    /**
     * Return API Payment Method creation request
     *
     * @return \Vobapay\Payment\Model\Request\PaymentMethod
     */
    public function getPaymentMethodRequest()
    {
        return new \Vobapay\Payment\Model\Request\PaymentMethod();
    }

    /**
     * Returns config value
     *
     * @param string $sParameterName
     * @return string
     */
    public function getConfigParam($sParameterName)
    {
        $aPaymentConfig = $this->getPaymentConfig();

        if (isset($aPaymentConfig[$sParameterName])) {
            return $aPaymentConfig[$sParameterName];
        }
        return false;
    }

    /**
     * Loads payment config if not loaded, otherwise returns preloaded config
     *
     * @return array
     */
    public function getPaymentConfig()
    {
        if ($this->aPaymentConfig === null) {
            $oPaymentConfig = oxNew(PaymentConfig::class);
            $this->aPaymentConfig = $oPaymentConfig->getPaymentConfig($this->getOxidPaymentId());
        }
        return $this->aPaymentConfig;
    }

    /**
     * Return Oxid payment id
     *
     * @return string
     */
    public function getOxidPaymentId()
    {
        return $this->sOxidPaymentId;
    }

    /**
     * Returns if the payment method only supports B2B orders
     *
     * @return bool
     */
    public function isOnlyB2BSupported()
    {
        return $this->blIsOnlyB2BSupported;
    }
}
