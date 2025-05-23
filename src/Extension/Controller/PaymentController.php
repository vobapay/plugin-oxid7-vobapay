<?php

namespace Vobapay\Payment\Extension\Controller;

use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Application\Model\Country;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Helper\Order as OrderHelper;
use Vobapay\Payment\Helper\Payment as PaymentHelper;

class PaymentController extends PaymentController_parent
{
    /**
     * Delete sess_challenge from session to trigger the creation of a new order when needed
     */
    public function init()
    {

        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
        $blvobapayIsRedirected = Registry::getSession()->getVariable('vobapayIsRedirected');
        if (!empty($sSessChallenge) && $blvobapayIsRedirected === true) {
            OrderHelper::getInstance()->cancelCurrentOrder();
        }
        Registry::getSession()->deleteVariable('vobapayIsRedirected');
        parent::init();
    }

    /**
     * Template variable getter. Returns paymentlist
     *
     * @return object
     */
    public function getPaymentList()
    {
        parent::getPaymentList();
        //$this - vobapayRemoveUnavailablePaymentMethods();
        return $this->_oPaymentList;
    }

    /**
     * @return string
     */
    public function validatepayment()
    {
        $mRet = parent::validatepayment();

        $sPaymentId = Registry::getRequest()->getRequestParameter('paymentid');
        $oPaymentModel = PaymentHelper::getInstance()->getVobapayPaymentModel($sPaymentId);

        if (!PaymentHelper::getInstance()->isVobapayPaymentMethod($sPaymentId)) {
            return $mRet;
        }

        try {
            if (!empty($oPaymentModel->getOxidPaymentId())) {
                Registry::getSession()->setVariable('vobapay_current_payment_method_id', $oPaymentModel->getOxidPaymentId());
            }
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx->getTraceAsString());
            $mRet = 'payment';
        }

        return $mRet;
    }

    /**
     * Removes vobapay payment methods which are not available for the current basket situations. The limiting factors can be:
     * 1. Payment method is not available for given billing country
     * 2. Payment method is not available for given basket currency
     * 3. Payment method has a B2B restriction and order does not belong to this category
     *
     * @return void
     */
    protected function RemoveUnavailablePaymentMethods()
    {
        $oPaymentHelper = PaymentHelper::getInstance();

        $oBasket = Registry::getSession()->getBasket();
        $sBillingCountryCode = $this->vobapayGetBillingCountry($oBasket);
        $sCurrency = $oBasket->getBasketCurrency()->name;

        foreach ($this->_oPaymentList as $oPayment) {
            if (method_exists($oPayment, 'isVobapayPaymentMethod') &&
                $oPayment->isVobapayPaymentMethod() === true) {
                $oVobapayPayment = $oPayment->getVobapayPaymentModel();

                if ($oVobapayPayment->vobapayIsMethodAvailableForCountry($sBillingCountryCode) === false ||
                    $oVobapayPayment->vobapayIsMethodAvailableForCurrency($sCurrency) === false ||
                    ($oVobapayPayment->isOnlyB2BSupported() === true && $this->vobapayIsB2BOrder($oBasket) === false)
                ) {
                    unset($this->_oPaymentList[$oPayment->getId()]);
                }
            }
        }
    }

    /**
     * Returns billing country code of current basket
     *
     * @param Basket $oBasket
     * @return string
     */
    protected function vobapayGetBillingCountry($oBasket)
    {
        $oUser = $oBasket->getBasketUser();

        $oCountry = oxNew(Country::class);
        $oCountry->load($oUser->oxuser__oxcountryid->value);

        if (!$oCountry->oxcountry__oxisoalpha2) {
            return '';
        }

        return $oCountry->oxcountry__oxisoalpha2->value;
    }

    /**
     * Returns if current order is being considered as a B2B order
     *
     * @param Basket $oBasket
     * @return bool
     */
    protected function vobapayIsB2BOrder($oBasket)
    {
        $oUser = $oBasket->getBasketUser();
        if (!empty($oUser->oxuser__oxcompany->value)) {
            return true;
        }
        return false;
    }
}
