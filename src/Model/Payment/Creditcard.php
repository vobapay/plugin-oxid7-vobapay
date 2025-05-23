<?php

namespace Vobapay\Payment\Model\Payment;

use Vobapay\Payment\Core\Enum\CaptureTypes;
use Vobapay\Payment\Helper\Config;
use Vobapay\Payment\Helper\Payment as PaymentHelper;

class Creditcard extends Base
{
    /**
     * Payment id in the oxid shop
     *
     * @var string
     */
    protected $sOxidPaymentId = Config::PLUGIN_VP_CREDITCARD;

    /**
     * Method code used for API request
     *
     * @var string
     */
    protected $sVobapayPaymentCode = 'CARD';

    /**
     * Determines custom config template if existing, otherwise false
     *
     * @var string|bool
     */
    protected $sCustomConfigTemplate = 'vobapay_config_creditcard';

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
        $sCapture = $this->getConfigParam('creditcard_capture_method');

        if (in_array($sCapture, CaptureTypes::getAll())) {
            $captureType = $sCapture;
        } else {
            $captureType = $this->getVobapayPaymentCapture();
        }

        if (PaymentHelper::getInstance()->containsSandboxUrl()) {
            $sPurpose = 'Test:0000';
        }

        $aParams = [
            "type" => $this->getVobapayPaymentCode(),
            "capture" => $captureType,
            "creditcard" =>
                [
                    "locale" => PaymentHelper::getInstance()->getLocale(),
                    "tds2_street" => $aInfo["street"] . " " . $aInfo["housenumber"],
                    "tds2_postcode" => $aInfo["postal_code"],
                    "tds2_city" => $aInfo["city"]
                ],
            "purpose" => $sPurpose
        ];

        return $aParams;
    }

    /**
     * Return the capture MANUAL value
     *
     * @return string
     */
    public function getCaptureManualValue()
    {
        return CaptureTypes::MANUAL;
    }

    /**
     * Return the capture AUTO value
     *
     * @return string
     */
    public function getCaptureAutoValue()
    {
        return CaptureTypes::AUTO;
    }
}
