<?php

namespace Vobapay\Payment\Controller;

use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;
use Vobapay\Payment\Helper\Payment;

class VobapayWebhook extends FrontendController
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
        // Get the JSON body from the request
        $jsonBody = file_get_contents('php://input');

        // Convert it into an associative array
        $requestData = json_decode($jsonBody, true);

        // Check if the JSON is valid
        if (json_last_error() === JSON_ERROR_NONE) {
            $sTransactionId = $requestData['payment_uuid'] ?? null;
            $sMerchantReference = $requestData['merchant_reference'] ?? null;
            $sStatus = $requestData['status'] ?? null;
        }

        if (!empty($sTransactionId)) {
            $oOrder = oxNew(Order::class);
            if ($oOrder->vobapayLoadOrderByTransactionId($sTransactionId) === true ) {
                $oOrder->vobapayGetPaymentModel()->getTransactionHandler()->processTransaction($oOrder);
            } else {
                // Throw HTTP error when order not found
                // For some payment methods the webhook is called before the order exists
                Registry::getUtils()->setHeader("HTTP/1.1 409 Conflict");
                Registry::getUtils()->showMessageAndExit("");
            }
        }
        /*switch ($event->type) {
            case 'payment_intent.succeeded':
                $sPaymentIntentId = $event->data->object->id;
                if (!empty($sPaymentIntentId)) {
                    $oOrder = oxNew(Order::class);
                    if ($oOrder->vobapayLoadOrderByTransactionId($sPaymentIntentId) === true) {
                        $oOrder->vobapayGetPaymentModel()->getTransactionHandler()->processTransaction($oOrder);
                    } else {
                        // Throw HTTP error when order not found, this will trigger vobapay to retry sending the status
                        // For some payment methods the webhook is called before the order exists
                        Registry::getUtils()->setHeader("HTTP/1.1 409 Conflict");
                        Registry::getUtils()->showMessageAndExit("");
                    }
                }
                break;
            case 'payment_intent.payment_failed' :
                $sPaymentIntentId = $event->data->object->id;
                if (!empty($sPaymentIntentId)) {
                    $oOrder = oxNew(Order::class);
                    if ($oOrder->vobapayLoadOrderByTransactionId($sPaymentIntentId) === true) {
                        //$oOrder->vobapaySetFolder('ORDERFOLDER_PROBLEMS');
                    }
                }
                break;
            case 'charge.refunded' :
                $sPaymentIntentId = $event->data->object->payment_intent;
                if (!empty($sPaymentIntentId)) {
                    $oOrder = oxNew(Order::class);
                    if ($oOrder->vobapayLoadOrderByTransactionId($sPaymentIntentId) === true) {
                        //$oOrder->vobapaySetFolder('ORDERFOLDER_FINISHED');
                    }
                }
                break;
            default:
                echo 'Received unknown event type ' . $event->type;
        }*/

        return $this->_sThisTemplate;
    }
}
