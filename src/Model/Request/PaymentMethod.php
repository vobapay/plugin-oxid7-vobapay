<?php

namespace Vobapay\Payment\Model\Request;

use OxidEsales\EshopCommunity\Core\Registry;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use Vobapay\Payment\Core\Enum\VpCompanyLegalForm;
use Vobapay\Payment\Helper\Config;
use Vobapay\Payment\Helper\Payment as PaymentHelper;
use Vobapay\Payment\Helper\Order as OrderHelper;

class PaymentMethod extends Base
{
    /**
     * Add needed parameters to the API request
     *
     * @param CoreOrder $oOrder
     * @param double $dAmount
     * @param string $sReturnUrl
     * @param string $sWebhookUrl
     * @return void
     */
    public function addRequestParameters(CoreOrder $oOrder, $dAmount, $sReturnUrl, $sWebhookUrl)
    {
        $oPaymentModel = $oOrder->vobapayGetPaymentModel();
        $aAmount = $this->getAmountParameters($oOrder, $dAmount);

        $aAmountParams = [
            "value" => $aAmount['value'],
            "currency" => $aAmount['currency']
        ];

        $sPurpose = OrderHelper::getInstance()->getPurpose($oOrder);
        $aBillingAddress = $this->getBillingAddressParameters($oOrder);

        if ($oPaymentModel->getOxidPaymentId() == Config::PLUGIN_VP_INSTALLMENTS ||
            $oPaymentModel->getOxidPaymentId() == Config::PLUGIN_VP_INVOICE) {

            $this->addParameter('consumer', $this->getCustomerParameters($oOrder));
            $this->addParameter('billing_address', $aBillingAddress);

            if ($oOrder->oxorder__oxdellname && $oOrder->oxorder__oxdellname->value != '') {
                $this->addParameter('shipping_address', $this->getShippingAddressParameters($oOrder));
            } else {
                $this->addParameter('shipping_address', $this->getBillingAddressParameters($oOrder));
            }

            $this->addParameter('order_lines', $this->getBasketItems($oOrder));
        }

        $oUser = $oOrder->getUser();

        if (!(string)empty($oUser->oxuser__oxcompany->value)) {
            $aCompanyParams = [
                "name" => $oUser->oxuser__oxcompany->value,
                "legalform" => VpCompanyLegalForm::SONSTIGE
            ];
            $this->addParameter('company', $aCompanyParams);
        }

        $this->addParameter('payment_method', $oPaymentModel->getPaymentMethodParams($aBillingAddress, $sPurpose));
        $this->addParameter('amount', $aAmountParams);
        $this->addParameter('merchant_tx_id', $oOrder->getId());
        $this->addParameter('url_success', $sReturnUrl);
        $this->addParameter('url_failed', $sReturnUrl);
        $this->addParameter('url_canceled', $sReturnUrl);
        $this->addParameter('url_webhook', $sWebhookUrl);
    }

    /**
     * Execute Request to vobapay API and return Response
     *
     * @return \Vobapay\Payment\Core\Service\RestClient
     * @throws \Exception
     */
    public function execute()
    {
        try {
            $oResponse = PaymentHelper::getInstance()->loadVobapayApi()->createPayment($this->getParameters());
        } catch (\Exception $oEx) {
            throw $oEx;
        }

        if (isset($oResponse['error']) && !empty($oResponse['error'])) {
            throw new \Exception($oResponse['error']);
        }

        return $oResponse;
    }
}
