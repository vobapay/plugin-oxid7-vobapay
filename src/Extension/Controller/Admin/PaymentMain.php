<?php

namespace Vobapay\Payment\Extension\Controller\Admin;

use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Helper\Order;
use Vobapay\Payment\Helper\Config;
use Vobapay\Payment\Model\PaymentConfig;

class PaymentMain extends PaymentMain_parent
{
    /**
     * Save payment parameters changes.
     *
     * @return void
     */
    public function save()
    {
        parent::save();

        $aVobapayParams = Registry::getRequest()->getRequestParameter(Config::PLUGIN_CODE);

        $oPaymentConfig = oxNew(PaymentConfig::class);
        $oPaymentConfig->savePaymentConfig($this->getEditObjectId(), $aVobapayParams);
    }

    /**
     * Return order status array
     *
     * @return array
     */
    public function vobapayGetOrderFolders()
    {
        return Order::getInstance()->vobapayGetOrderFolders();
    }
}
