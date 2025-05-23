<?php

namespace Vobapay\Payment\Model\Payment;

use Vobapay\Payment\Helper\Config;

class Eps extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = Config::PLUGIN_VP_EPS;

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sVobapayPaymentCode = 'EPS';

    /** @var array */
    protected $aCurrencyRestrictedTo = ['EUR'];

    /**
     * Determines custom frontend template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomFrontendTemplate = 'vobapay';

    /**
     * Return PaymentMethod parameters specific to the given payment type
     *
     * @param array $aInfo
     * @param string $sPurpose
     * @return array
     */
    public function getPaymentMethodParams(array $aInfo = array(), $sPurpose)
    {
        $aParams = [
            "type" => $this->getVobapayPaymentCode(),
            "capture" => $this->getVobapayPaymentCapture(),
            "purpose" => $sPurpose
        ];

        return $aParams;
    }
}
