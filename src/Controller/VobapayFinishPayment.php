<?php

namespace Vobapay\Payment\Controller;

use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;

class VobapayFinishPayment extends FrontendController
{
    /**
     * @var string
     */
    protected $_sThisTemplate = '@vobapay/vobapaywebhook';

    /**
     * The render function
     */
    public function render()
    {
        $sRedirectUrl = Registry::getConfig()->getSslShopUrl() . "?cl=basket";

        $oOrder = $this->getOrder();
        if ($oOrder !== false) {
            $oOrder->vobapayReinitializePayment();
            $sRedirectUrl = Registry::getConfig()->getSslShopUrl() . "?cl=success";
        }

        Registry::getUtils()->redirect($sRedirectUrl);
    }

    /**
     * Returns order or false if no id given or order not eligible
     *
     * @return bool|object
     */
    protected function getOrder()
    {
        $sOrderId = Registry::getRequest()->getRequestParameter('id');
        if ($sOrderId) {
            $oOrder = oxNew(Order::class);
            $oOrder->load($sOrderId);
            if ($oOrder->getId() && $oOrder->vobapayIsEligibleForPaymentFinish()) {
                return $oOrder;
            }
        }
        return false;
    }
}
