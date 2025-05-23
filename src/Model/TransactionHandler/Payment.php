<?php

namespace Vobapay\Payment\Model\TransactionHandler;

use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Core\Enum\OxOrderTransStatus;
use Vobapay\Payment\Core\Enum\VpPaymentStatus;
use Vobapay\Payment\Helper\Payment as PaymentHelper;

class Payment extends Base
{
    /**
     * Handle order according to the given transaction status
     *
     * @param array $aTransaction
     * @param Order $oOrder
     * @param string $sType
     * @return array
     */
    protected function handleTransactionStatus(array $aTransaction, Order $oOrder, $sType)
    {
        $blSuccess = false;
        $sStatus = $aTransaction["status"];
        $sStatusCode = $aTransaction["status_code"];
        $oOrder->vobapaySetVobapayStatus($sStatus);

        if ($sStatus == VpPaymentStatus::CANCELED || $sStatus == VpPaymentStatus::FAILED ||
            $sStatus == VpPaymentStatus::EXPIRED) {
            $oOrder->cancelOrder();
            return ['success' => false, 'status' => $sStatus, 'status_code' => $sStatusCode];
        }

        if ($sStatus == VpPaymentStatus::PAID) {
            if ($oOrder->vobapayIsPaid() === false && $sType == 'webhook') {
                if ($oOrder->oxorder__oxstorno->value == 1) {
                    $oOrder->vobapayUncancelOrder();
                }
            }

            $oOrder->vobapayMarkAsPaid();
            $oOrder->vobapaySetTransStatus(OxOrderTransStatus::OK);
            $oOrder->vobapaySetFolder(PaymentHelper::getInstance()->getShopConfVar('vp_statusprocessing'));

            $blSuccess = true;
        } elseif ($sStatus == VpPaymentStatus::AUTHORIZED) {
            $blSuccess = true;
        } elseif ($sStatus == VpPaymentStatus::PENDING) {
            $blSuccess = true;
        }

        return ['success' => $blSuccess, 'status' => $sStatus, 'status_code' => $sStatusCode];
    }
}
