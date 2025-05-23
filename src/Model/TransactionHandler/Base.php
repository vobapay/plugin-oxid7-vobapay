<?php

namespace Vobapay\Payment\Model\TransactionHandler;

use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Helper\Payment as PaymentHelper;

abstract class Base
{
    /**
     * Logfile name
     *
     * @var string
     */
    protected $sLogFileName = 'vobapayTransactions.log';

    /**
     * Process transaction status after payment and in the webhook
     *
     * @param Order $oOrder
     * @param string $sType
     * @return array
     */
    public function processTransaction(Order $oOrder, $sType = 'webhook')
    {
        try {
            $oTransaction = PaymentHelper::getInstance()->loadVobapayApi()->getPayment($oOrder->oxorder__oxtransid->value);
            $aResult = $this->handleTransactionStatus($oTransaction, $oOrder, $sType);
        } catch (\Exception $exc) {
            $aResult = ['success' => false, 'status' => 'exception', 'error' => $exc->getMessage()];
        }

        $aResult['transactionId'] = $oOrder->oxorder__oxtransid->value;
        $aResult['orderId'] = $oOrder->getId();
        $aResult['type'] = $sType;

        $this->logResult($aResult);

        return $aResult;
    }

    /**
     * Log transaction status to log file if enabled
     *
     * @param array $aResult
     * @return void
     */
    protected function logResult($aResult)
    {
        $sMessage = (new \DateTimeImmutable())->format('Y-m-d H:i:s') . " Transaction handled: " . print_r($aResult, true) . " \n";

        $sLogFilePath = getShopBasePath() . '/log/' . $this->sLogFileName;
        $oLogFile = fopen($sLogFilePath, "a");
        if ($oLogFile) {
            fwrite($oLogFile, $sMessage);
            fclose($oLogFile);
        }

    }
}
