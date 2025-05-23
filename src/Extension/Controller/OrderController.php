<?php

namespace Vobapay\Payment\Extension\Controller;

use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Core\Enum\VpPaymentStatus;
use Vobapay\Payment\Helper\Order as OrderHelper;

class OrderController extends OrderController_parent
{
    /**
     * Delete sess_challenge from session to trigger the creation of a new order when needed
     */
    public function render()
    {
        $sSessChallenge = Registry::getSession()->getVariable('sess_challenge');
        $blvobapayIsRedirected = Registry::getSession()->getVariable('vobapayIsRedirected');
        if (!empty($sSessChallenge) && $blvobapayIsRedirected === true) {
            OrderHelper::getInstance()->cancelCurrentOrder();
        }
        Registry::getSession()->deleteVariable('vobapayIsRedirected');
        return parent::render();
    }

    /**
     *
     * @return string
     */
    public function handleVobapayReturn()
    {
        $oPayment = $this->getPayment();
        if ($oPayment && $oPayment->isVobapayPaymentMethod()) {
            Registry::getSession()->deleteVariable('vobapayIsRedirected');

            $oOrder = $this->vobapayGetOrder();
            if (!$oOrder) {
                return $this->redirectWithError('VOBAPAY_ERROR_ORDER_NOT_FOUND');
            }

            $sTransactionId = $oOrder->oxorder__oxtransid->value;
            if (empty($sTransactionId)) {
                return $this->redirectWithError('VOBAPAY_ERROR_TRANSACTIONID_NOT_FOUND');
            }

            $aResult = $oOrder->vobapayGetPaymentModel()->getTransactionHandler()->processTransaction($oOrder, 'success');

            if ($aResult['success'] === false) {
                Registry::getSession()->deleteVariable('sess_challenge');
                $sErrorIdent = 'VOBAPAY_ERROR_SOMETHING_WENT_WRONG';
                if ($aResult['status'] == VpPaymentStatus::CANCELED) {
                    $sErrorIdent = 'VOBAPAY_ERROR_ORDER_CANCELED';
                } elseif ($aResult['status'] == VpPaymentStatus::FAILED) {
                    $sErrorIdent = 'VOBAPAY_ERROR_ORDER_FAILED';
                }
                return $this->redirectWithError($sErrorIdent);
            }

            // else - continue to parent::execute since success must be true
        }
        $sReturn = parent::execute();

        if (Registry::getSession()->getVariable('vobapayReinitializePaymentMode')) {
            Registry::getSession()->deleteVariable('usr'); // logout user since the payment link should not be seen as a successful login
        }

        Registry::getSession()->deleteVariable('vobapayReinitializePaymentMode');

        return $sReturn;
    }

    /**
     * Load previously created order
     *
     * @return Order|false
     */
    protected function vobapayGetOrder()
    {
        $sOrderId = Registry::getSession()->getVariable('sess_challenge');
        if (!empty($sOrderId)) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            if ($oOrder->isLoaded() === true) {
                return $oOrder;
            }
        }
        return false;
    }

    /**
     * Writes error-status to session and redirects to payment page
     *
     * @param string $sErrorLangIdent
     * @return false
     */
    protected function redirectWithError($sErrorLangIdent)
    {
        Registry::getSession()->setVariable('payerror', -50);
        Registry::getSession()->setVariable('payerrortext', Registry::getLang()->translateString($sErrorLangIdent));
        Registry::getUtils()->redirect(Registry::getConfig()->getCurrentShopUrl() . 'index.php?cl=payment');
        return false; // execution ends with redirect - return used for unit tests
    }
}
