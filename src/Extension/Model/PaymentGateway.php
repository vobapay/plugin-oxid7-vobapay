<?php

namespace Vobapay\Payment\Extension\Model;

use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Core\Enum\VpHttpStatus;
use Vobapay\Payment\Core\Enum\VpPaymentStatus;
use Vobapay\Payment\Helper\Payment as PaymentHelper;

class PaymentGateway extends PaymentGateway_parent
{
    /**
     * OXID URL parameters to copy from initial order execute request
     *
     * @var array
     */
    protected $aVobapayUrlCopyParameters = [
        'stoken',
        'sDeliveryAddressMD5',
        'oxdownloadableproductsagreement',
        'oxserviceproductsagreement',
    ];

    /**
     * Initiate vobapay payment functionality
     *
     * Executes payment, returns true on success.
     *
     * @param double $dAmount Goods amount
     * @param object $oOrder User ordering object
     *
     * @extend executePayment
     * @return bool
     */
    public function executePayment($dAmount, &$oOrder)
    {
        if (!PaymentHelper::getInstance()->isVobapayPaymentMethod($oOrder->oxorder__oxpaymenttype->value)) {
            return parent::executePayment($dAmount, $oOrder);
        }
        return $this->handleVobapayPayment($oOrder, $dAmount);
    }

    /**
     * Execute vobapay API request and redirect to vobapay for payment
     *
     * @param CoreOrder $oOrder
     * @param double $dAmount
     * @return bool
     */
    protected function handleVobapayPayment(CoreOrder &$oOrder, $dAmount)
    {
        $oOrder->vobapaySetOrderNumber();

        try {
            $oVobapayPaymentModel = $oOrder->vobapayGetPaymentModel();
            $sWebhookUrl = PaymentHelper::getInstance()->getWebhookUrl();
            $sPaymentMethodId = Registry::getSession()->getVariable('vobapay_current_payment_method_id');

            $oVobapayPaymentRequest = $oVobapayPaymentModel->getPaymentMethodRequest();
            $oVobapayPaymentRequest->addRequestParameters($oOrder, $dAmount, $this->getRedirectUrl(), $sWebhookUrl);
            $oVobapayPayment = $oVobapayPaymentRequest->execute();

            if ($oVobapayPayment["status_code"] == VpHttpStatus::CREATED) {
                $oOrder->vobapaySetTransactionId($oVobapayPayment["payment_uuid"]);
                $oOrder->vobapaySetVobapayStatus($oVobapayPayment["status"]);

                if (isset($oVobapayPayment["redirect_url"]) && $oVobapayPayment["status"] == VpPaymentStatus::REDIRECT) {
                    Registry::getSession()->setVariable('vobapayIsRedirected', true);
                    Registry::getUtils()->redirect($oVobapayPayment["redirect_url"]);
                } else {
                    Registry::getSession()->setVariable('vobapayIsRedirected', false);
                }
            } else {
                $this->_iLastErrorNo = $oVobapayPayment["status_code"];
                $this->_sLastError = $oVobapayPayment["error"];
                return false;
            }
        } catch (\Exception $exc) {
            $this->_iLastErrorNo = $exc->getCode();
            $this->_sLastError = $exc->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Generate a return url with all necessary return flags
     *
     * @return string
     */
    protected function getRedirectUrl()
    {
        $sShopId = Registry::getConfig()->getShopId();
        $sBaseUrl = Registry::getConfig()->getCurrentShopUrl() . 'index.php?cl=order&fnc=handleVobapayReturn&shp=' . $sShopId;

        return $sBaseUrl . $this->vobapayGetAdditionalParameters();
    }

    /**
     * Collect parameters from the current order execute call and add them to the return URL
     * Also add parameters needed for the return process
     *
     * @return string
     */
    protected function vobapayGetAdditionalParameters()
    {
        $oRequest = Registry::getRequest();
        $oSession = Registry::getSession();

        $sAddParams = '';

        foreach ($this->aVobapayUrlCopyParameters as $sParamName) {
            $sValue = $oRequest->getRequestEscapedParameter($sParamName);
            if (!empty($sValue)) {
                $sAddParams .= '&' . $sParamName . '=' . $sValue;
            }
        }

        $sSid = $oSession->sid(true);
        if ($sSid != '') {
            $sAddParams .= '&' . $sSid;
        }

        if (!$oRequest->getRequestEscapedParameter('stoken')) {
            $sAddParams .= '&stoken=' . $oSession->getSessionChallengeToken();
        }
        $sAddParams .= '&ord_agb=1';
        $sAddParams .= '&rtoken=' . $oSession->getRemoteAccessToken();

        return $sAddParams;
    }
}
