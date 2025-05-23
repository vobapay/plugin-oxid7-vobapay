<?php

namespace Vobapay\Payment\Extension\Controller\Admin;

use Vobapay\Payment\Helper\Order;
use OxidEsales\Eshop\Core\Registry;

class ModuleConfiguration extends ModuleConfiguration_parent
{
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
