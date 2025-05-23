<?php

namespace Vobapay\Payment\Controller\Admin;

use OxidEsales\Eshop\Application\Controller\Admin\AdminDetailsController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Field;
use Vobapay\Payment\Core\Enum\CaptureTypes;
use Vobapay\Payment\Core\Enum\VpHttpStatus;
use Vobapay\Payment\Core\Enum\VpPaymentStatus;
use Vobapay\Payment\Helper\Config;
use Vobapay\Payment\Helper\Payment as PaymentHelper;
use Vobapay\Payment\Core\Service\RestClient;

class OrderOperations extends AdminDetailsController
{
    /**
     * Template to be used
     *
     * @var string
     */
    protected $_sTemplate = "@vobapay/vobapay_order_operations";

    /**
     * Order object
     *
     * @var Order|null
     */
    protected $_oOrder = null;

    /**
     * Error message property
     *
     * @var string|bool
     */
    protected $_sErrorMessage = false;

    /**
     * Flag if a successful refund was executed
     *
     * @var bool|null
     */
    protected $_blSuccessfulRefund = null;

    /**
     * Flag if a successful capture was executed
     *
     * @var bool|null
     */
    protected $_blSuccessfulCapture = null;

    /**
     * Array of refund items
     *
     * @var array|null
     */
    protected $_aRefundItems = null;

    /**
     * vobapay api
     *
     * @var RestClient
     */
    protected $_oVobapayApi = null;

    /**
     * Amount available for refund
     *
     * @var bool|null
     */
    protected $_dAvailableRefund = 0;

    /**
     * Amount available for capture
     *
     * @var bool|null
     */
    protected $_dAvailableCapture = 0;

    /**
     * Amount refunded
     *
     * @var bool|null
     */
    protected $_dTotalRefund = 0;

    /**
     * Amount captured
     *
     * @var bool|null
     */
    protected $_dTotalCapture = 0;

    /**
     * Property to store payment data from the API
     *
     * @var array|null
     */
    protected $_aPaymentData = null;

    /**
     * Property to store evaluated payment options
     *
     * @var array|null
     */
    protected $_aEvaluatedPaymentOptions = null;

    public function __construct()
    {
        parent::__construct();
        $this->_oVobapayApi = $this->getVobapayApiRequestModel();
    }

    /**
     * Returns Vobapay payment Api
     *
     * @return RestClient
     */
    protected function getVobapayApiRequestModel()
    {
        return PaymentHelper::getInstance()->loadVobapayApi();
    }

    /**
     * Main render method
     *
     * @return string
     */
    public function render()
    {
        parent::render();

        $oOrder = $this->getOrder();
        if ($oOrder) {
            $this->_aViewData["edit"] = $oOrder;
        }

        return $this->_sTemplate;
    }

    /**
     * Loads current order
     *
     * @return null|object|Order
     */
    public function getOrder()
    {
        if ($this->_oOrder === null) {
            $oOrder = oxNew(Order::class);

            $soxId = $this->getEditObjectId();
            if (isset($soxId) && $soxId != "-1") {
                $oOrder->load($soxId);

                $this->_oOrder = $oOrder;
            }
        }
        return $this->_oOrder;
    }

    /**
     * Execute refund action
     *
     * @return void
     */
    public function refund()
    {
        try {
            $oVobapayApi = $this->_oVobapayApi;
            $sTransactionId = $this->getOrder()->oxorder__oxtransid->value;
            $aParams = $this->getParameters('refund_amount');
            $oResponse = $oVobapayApi->refundPayment($sTransactionId, $aParams);

            $this->_blSuccessfulRefund = false;

            if ($oResponse["status_code"] == VpHttpStatus::CREATED &&
                ($oResponse["refund_status"] == VpPaymentStatus::REFUNDED ||
                    $oResponse["refund_status"] == VpPaymentStatus::PARTIAL_REFUNDED) &&
                ($oResponse["status"] == VpPaymentStatus::REFUNDED ||
                    $oResponse["status"] == VpPaymentStatus::PARTIAL_REFUNDED)) {
                $this->_blSuccessfulRefund = true;
                //$this->markOrderAsFullyRefunded();
            } else {
                $this->setErrorMessage(Registry::getLang()->translateString('VOBAPAY_REFUND_FAILED'));
                $this->_blSuccessfulRefund = false;
            }
        } catch (\Exception $oEx) {
            $this->setErrorMessage($oEx->getMessage());
            $this->_blSuccessfulRefund = false;
        }
    }

    /**
     * Generate request parameter array
     *
     * @return array
     */
    protected function getParameters($sAmountField)
    {
        //$dAmount = $this->getOrder()->oxorder__oxtotalordersum->value;
        //$dTaxAmount = $this->getOrder()->oxorder__oxartvatprice1->value;

        if (!empty(Registry::getRequest()->getRequestEscapedParameter($sAmountField))) {
            $dAmount = Registry::getRequest()->getRequestEscapedParameter($sAmountField);
        }

        $aAmountParams = [
            "value" => PaymentHelper::getInstance()->priceInCent($dAmount),
            "currency" => $this->getOrder()->oxorder__oxcurrency->value
        ];

        $aParams["amount"] = $aAmountParams;

        return $aParams;
    }

    /**
     * Sets error message
     *
     * @param string $sError
     */
    public function setErrorMessage($sError)
    {
        $this->_sErrorMessage = $sError;
    }

    /**
     * Execute capture action
     *
     * @return void
     */
    public function capture()
    {
        try {
            $oVobapayApi = $this->_oVobapayApi;
            $sTransactionId = $this->getOrder()->oxorder__oxtransid->value;
            $aParams = $this->getParameters('capture_amount');
            $oResponse = $oVobapayApi->capturePayment($sTransactionId, $aParams);

            $this->_blSuccessfulCapture = false;

            if ($oResponse["status_code"] == VpHttpStatus::CREATED &&
                $oResponse["capture_status"] == VpPaymentStatus::AUTHORIZED &&
                $oResponse["status"] == VpPaymentStatus::AUTHORIZED) {
                $this->_blSuccessfulCapture = true;
                //$this->markOrderAsFullyRefunded();
            } else {
                $this->setErrorMessage(Registry::getLang()->translateString('VOBAPAY_CAPTURE_FAIL'));
                $this->_blSuccessfulCapture = false;
            }
        } catch (\Exception $oEx) {
            $this->setErrorMessage($oEx->getMessage());
            $this->_blSuccessfulCapture = false;
        }
    }

    /**
     * Checks if there were previous partial refunds and therefore full refund is not available anymore
     *
     * @return bool
     */
    public function isFullRefundAvailable()
    {
        $oOrder = $this->getOrder();
        foreach ($oOrder->getOrderArticles() as $orderArticle) {
            if ((double)$orderArticle->oxorderarticles__vobapayamountrefunded->value > 0 ||
                $orderArticle->oxorderarticles__vobapayquantityrefunded->value > 0) {
                return false;
            }
        }

        if ($oOrder->oxorder__vobapaydelcostrefunded->value > 0
            || $oOrder->oxorder__vobapaypaycostrefunded->value > 0
            || $oOrder->oxorder__vobapaywrapcostrefunded->value > 0
            || $oOrder->oxorder__vobapaygiftcardrefunded->value > 0
            || $oOrder->oxorder__vobapayvoucherdiscountrefunded->value > 0
            || $oOrder->oxorder__vobapaydiscountrefunded->value > 0) {
            return false;
        }
        return true;
    }

    /**
     * Check vobapay API if order is refundable
     *
     * @return bool
     */
    public function isOrderRefundable()
    {
        $aEvaluateRes = $this->getEvaluatedPaymentOptions();

        if ($aEvaluateRes["canRefund"]) {
            return true;
        }

        return false;
    }

    /**
     * Evaluates payment options using the retrieved payment data, caching the result.
     *
     * @return array
     */
    protected function getEvaluatedPaymentOptions()
    {
        if ($this->_aEvaluatedPaymentOptions === null) {
            $this->_aEvaluatedPaymentOptions = $this->evaluatePaymentOptions($this->getPaymentData());
        }

        return $this->_aEvaluatedPaymentOptions;
    }

    /**
     * Evaluates whether a payment allows capture or refund operations.
     *
     * @param array $paymentData The payment details as an associative array.
     * @return array Returns an array with information about capture and refund possibilities.
     */
    protected function evaluatePaymentOptions(array $paymentData): array
    {
        // Default values
        $canCapture = false;
        $predefinedCaptureAmount = 0;
        $partialCaptureSupported = false;

        $canRefund = false;
        $predefinedRefundAmount = 0;
        $partialRefundSupported = false;
        $maxRefundAmount = 0;

        $alreadyCapturedAmount = $paymentData['captured']['amount']['value'] ?? 0;
        $alreadyRefundedAmount = $paymentData['refunded']['amount']['value'] ?? 0;
        $totalAmount = $paymentData['amount']['value'] ?? 0;
        $captureSupportType = $paymentData['capture']['capture_supported'] ?? '';
        $refundSupportType = $paymentData['refund']['refund_supported'] ?? '';

        // Check if capture is possible
        if (!empty($paymentData['capture'])) {
            $predefinedCaptureAmount = $paymentData['capture']['amount']['value'] ?? 0;

            // Allow capturing based on support type
            if ($captureSupportType === 'multiple_partial') {
                $partialCaptureSupported = true;
            } elseif ($captureSupportType === 'single_partial' && $alreadyCapturedAmount == 0) {
                $partialCaptureSupported = true;
            }
        }

        // Calculate the maximum capturable amount
        $maxCaptureAmount = max(0, $totalAmount - $alreadyCapturedAmount);

        // Capture is allowed if maxCaptureAmount is greater than zero and capture is supported
        $canCapture = $maxCaptureAmount > 0 && $partialCaptureSupported;

        // Check if refund is possible
        if (!empty($paymentData['refund'])) {
            $predefinedRefundAmount = $paymentData['refund']['amount']['value'] ?? 0;

            // Allow refunding based on support type
            if ($refundSupportType === 'multiple_partial') {
                $partialRefundSupported = true;
            } elseif ($refundSupportType === 'single_partial' && $alreadyRefundedAmount == 0) {
                $partialRefundSupported = true;
            }
        }

        // Calculate the maximum refundable amount
        $maxRefundAmount = max(0, $alreadyCapturedAmount - $alreadyRefundedAmount);

        // Refund is allowed if maxRefundAmount is greater than zero and refund is supported
        $canRefund = $maxRefundAmount > 0 && $partialRefundSupported;

        $this->setAvailableCapturable($maxCaptureAmount);
        $this->setTotalCapture($alreadyCapturedAmount);
        $this->setAvailableRefundable($maxRefundAmount);
        $this->setTotalRefund($alreadyRefundedAmount);

        return [
            'canCapture' => $canCapture,
            'predefinedCaptureAmount' => $predefinedCaptureAmount,
            'partialCaptureSupported' => $partialCaptureSupported,
            'alreadyCapturedAmount' => $alreadyCapturedAmount,
            'maxCaptureAmount' => $maxCaptureAmount,

            'canRefund' => $canRefund,
            'predefinedRefundAmount' => $predefinedRefundAmount,
            'partialRefundSupported' => $partialRefundSupported,
            'alreadyRefundedAmount' => $alreadyRefundedAmount,
            'maxRefundAmount' => $maxRefundAmount,
        ];
    }

    /**
     * Set the available capturable amount from vobapay Api
     *
     * @return double
     */
    public function setAvailableCapturable($dPrice)
    {
        $this->_dAvailableCapture = $dPrice;
    }

    /**
     * Set the captured amount from vobapay Api
     *
     * @return double
     */
    public function setTotalCapture($dPrice)
    {
        $this->_dTotalCapture = $dPrice;
    }

    /**
     * Set the available refundable amount from vobapay Api
     *
     * @return double
     */
    public function setAvailableRefundable($dPrice)
    {
        $this->_dAvailableRefund = $dPrice;
    }

    /**
     * Set the refunded amount from vobapay Api
     *
     * @return double
     */
    public function setTotalRefund($dPrice)
    {
        $this->_dTotalRefund = $dPrice;
    }

    /**
     * Retrieves payment data from the API, reusing the response if already obtained.
     *
     * @return array
     */
    protected function getPaymentData()
    {
        if ($this->_aPaymentData === null) {
            $this->_aPaymentData = $this->_oVobapayApi->getPayment($this->getOrder()->oxorder__oxtransid->value);
        }
        return $this->_aPaymentData;
    }

    /**
     * Returns if refund was successful
     *
     * @return bool
     */
    public function wasRefundSuccessful()
    {
        return $this->_blSuccessfulRefund;
    }

    /**
     * Check vobapay API if order is capturable
     *
     * @return bool
     */
    public function isOrderCapturable()
    {
        $sOxidPaymentId = $this->getOrder()->oxorder__oxpaymenttype->value;
        $aPaymentModel = PaymentHelper::getInstance()->getVobapayPaymentModel($sOxidPaymentId);

        if ($sOxidPaymentId != Config::PLUGIN_VP_CREDITCARD) {
            return false;
        } elseif ($sOxidPaymentId == Config::PLUGIN_VP_CREDITCARD) {
            $sCaptureType = $aPaymentModel->getConfigParam('creditcard_capture_method');

            if ($sCaptureType == CaptureTypes::AUTO) {
                return false;
            }
        }
        $aEvaluateRes = $this->getEvaluatedPaymentOptions();

        if ($aEvaluateRes["canCapture"]) {
            return true;
        }

        return false;
    }

    /**
     * Returns if capture was successful
     *
     * @return bool
     */
    public function wasCaptureSuccessful()
    {
        return $this->_blSuccessfulCapture;
    }

    /**
     * Checks if order was payed with vobapay
     *
     * @return bool
     */
    public function isVobapayOrder()
    {
        return PaymentHelper::getInstance()->isVobapayPaymentMethod($this->getOrder()->oxorder__oxpaymenttype->value);
    }

    /**
     * Returns errormessage
     *
     * @return bool|string
     */
    public function getErrorMessage()
    {
        return $this->_sErrorMessage;
    }

    /**
     * Returns available refundable amount from vobapay Api
     *
     * @return double
     */
    public function getAvailableRefundable()
    {
        $dPrice = $this->_dAvailableRefund;
        $dFormatPrice = $this->getFormatedPrice($dPrice);

        return $dFormatPrice;
    }

    /**
     * Get amount from api formatted
     *
     * @return string
     */
    public function getFormatedPrice($dPrice)
    {
        $oCurrency = Registry::getConfig()->getCurrencyObject($this->getOrder()->oxorder__oxcurrency->value);
        $dPriceFromCent = PaymentHelper::getInstance()->priceFromCent($dPrice);

        return Registry::getLang()->formatCurrency($dPriceFromCent, $oCurrency);
    }

    /**
     * Get amount from order formatted
     *
     * @return string
     */
    public function getOrderFormatedPrice($dPrice)
    {
        $oCurrency = Registry::getConfig()->getCurrencyObject($this->getOrder()->oxorder__oxcurrency->value);

        return Registry::getLang()->formatCurrency($dPrice, $oCurrency);
    }

    /**
     * Returns available capturable amount from vobapay Api
     *
     * @return double
     */
    public function getAvailableCapturable()
    {
        $dPrice = $this->_dAvailableCapture;
        $dFormatPrice = $this->getFormatedPrice($dPrice);

        return $dFormatPrice;
    }

    /**
     * Returns captured amount from vobapay Api
     *
     * @return double
     */
    public function getTotalCapture()
    {
        $dPrice = $this->_dTotalCapture;
        return $this->getFormatedPrice($dPrice);
    }

    /**
     * Returns refunded amount from vobapay Api
     *
     * @return double
     */
    public function getTotalRefund()
    {
        $dPrice = $this->_dTotalRefund;
        return $this->getFormatedPrice($dPrice);
    }

    /**
     * Fills refunded db-fields with full costs
     *
     * @return void
     */
    protected function markOrderAsFullyRefunded()
    {
        $oOrder = $this->getOrder();
        $oOrder->oxorder__vobapaydelcostrefunded = new Field($oOrder->oxorder__oxdelcost->value);
        $oOrder->oxorder__vobapaypaycostrefunded = new Field($oOrder->oxorder__oxpaycost->value);
        $oOrder->oxorder__vobapaywrapcostrefunded = new Field($oOrder->oxorder__oxwrapcost->value);
        $oOrder->oxorder__vobapaygiftcardrefunded = new Field($oOrder->oxorder__oxgiftcardcost->value);
        $oOrder->oxorder__vobapayvoucherdiscountrefunded = new Field($oOrder->oxorder__oxvoucherdiscount->value);
        $oOrder->oxorder__vobapaydiscountrefunded = new Field($oOrder->oxorder__oxdiscount->value);
        $oOrder->save();

        foreach ($this->getOrder()->getOrderArticles() as $oOrderArticle) {
            $oOrderArticle->oxorderarticles__vobapayamountrefunded = new Field($oOrderArticle->oxorderarticles__oxbrutprice->value);
            $oOrderArticle->save();
        }

        $this->_oOrder = $oOrder; // update order for renderering the page
        $this->_aRefundItems = null;
    }
}
