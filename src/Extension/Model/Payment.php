<?php

namespace Vobapay\Payment\Extension\Model;

use Vobapay\Payment\Helper\Payment as PaymentHelper;

class Payment extends Payment_parent
{
    /**
     * Return vobapay payment model
     *
     * @return \Vobapay\Payment\Model\Payment\Base
     */
    public function getVobapayPaymentModel()
    {
        if ($this->isVobapayPaymentMethod()) {
            return PaymentHelper::getInstance()->getVobapayPaymentModel($this->getId());
        }
        return null;
    }

    /**
     * Check if given payment method is a vobapay method
     *
     * @return bool
     */
    public function isVobapayPaymentMethod()
    {
        return PaymentHelper::getInstance()->isVobapayPaymentMethod($this->getId());
    }

    /**
     * Return the logo to display for the payment method.
     *
     * @return string
     */
    public function getPaymentLogo()
    {
        return PaymentHelper::getInstance()->getPaymentLogo($this->getId());
    }
}
