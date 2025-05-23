<?php

namespace Vobapay\Payment\Extension\Model;

use OxidEsales\Eshop\Application\Model\Basket;
use OxidEsales\Eshop\Core\DatabaseProvider;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Core\Enum\OxOrderTransStatus;
use Vobapay\Payment\Core\Enum\VpPaymentStatus;
use Vobapay\Payment\Helper\Payment as PaymentHelper;
use Vobapay\Payment\Model\Payment\Base;

class Order extends Order_parent
{
    /**
     * Toggles certain behaviours in finalizeOrder for when the customer returns after the payment
     *
     * @var bool
     */
    protected $blVobapayFinalizeReturnMode = false;

    /**
     * Toggles certain behaviours in finalizeOrder for when order is being finished automatically
     * because customer did not come back to shop
     *
     * @var bool
     */
    protected $blVobapayFinishOrderReturnMode = false;

    /**
     * Toggles certain behaviours in finalizeOrder for when the the payment is being reinitialized at a later point in time
     *
     * @var bool
     */
    protected $blVobapayReinitializePaymentMode = false;

    /**
     * Temporary field for saving the order nr
     *
     * @var int|null
     */
    protected $vobapayTmpOrderNr = null;

    /**
     * State is saved to prevent order being set to transstatus OK during recalculation
     *
     * @var bool|null
     */
    protected $vobapayRecalculateOrder = null;

    /**
     * Used to trigger the _setNumber() method before the payment-process during finalizeOrder to have the order-number there already
     *
     * @return void
     */
    public function vobapaySetOrderNumber()
    {
        if (!isset($this->oxorder__oxordernr) || !is_object($this->oxorder__oxordernr) || !$this->oxorder__oxordernr->value) {
            $this->setNumber();
        }
    }

    /**
     * Tries to fetch and set next record number in DB. Returns true on success
     *
     * @return bool
     */
    protected function setNumber()
    {
        if ($this->blVobapayFinalizeReturnMode === false &&
            $this->blVobapayReinitializePaymentMode === false && $this->vobapayTmpOrderNr === null) {
            return parent::setNumber();
        }

        if (!$this->oxorder__oxordernr instanceof Field) {
            $this->oxorder__oxordernr = new Field($this->vobapayTmpOrderNr);
        } else {
            $this->oxorder__oxordernr->value = $this->vobapayTmpOrderNr;
        }

        return true;
    }

    /**
     * Returns if the order is marked as paid, since OXID doesnt have a proper flag
     *
     * @return bool
     */
    public function vobapayIsPaid()
    {
        if (!empty($this->oxorder__oxpaid->value) && $this->oxorder__oxpaid->value != "0000-00-00 00:00:00") {
            return true;
        }
        return false;
    }

    /**
     * Mark order as paid
     *
     * @return void
     */
    public function vobapayMarkAsPaid()
    {
        $sDate = date('Y-m-d H:i:s');

        $sQuery = "UPDATE oxorder SET oxpaid = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sDate, $this->getId()));

        $this->oxorder__oxpaid = new Field($sDate);
    }

    /**
     * Save transaction id in order object
     *
     * @param string $sTransactionId
     * @return void
     */
    public function vobapaySetTransactionId($sTransactionId)
    {
        DatabaseProvider::getDb()->execute('UPDATE oxorder SET oxtransid = ? WHERE oxid = ?', array($sTransactionId, $this->getId()));

        $this->oxorder__oxtransid = new Field($sTransactionId);
    }

    /**
     * Save vobapay status in order object
     *
     * @param string $sStatus
     * @return void
     */
    public function vobapaySetVobapayStatus($sStatus)
    {
        DatabaseProvider::getDb()->execute('UPDATE oxorder SET vobapaystatus = ? WHERE oxid = ?', array($sStatus, $this->getId()));

        $this->oxorder__vobapaystatus = new Field($sStatus);
    }

    /**
     * Extension: Order already existing because order was created before the user was redirected to vobapay,
     * therefore no stock validation needed. Otherwise an exception would be thrown on return when last product in stock was bought
     *
     * @param object $oBasket basket object
     */
    public function validateStock($oBasket)
    {
        if ($this->blVobapayFinalizeReturnMode === false) {
            return parent::validateStock($oBasket);
        }
    }

    /**
     * Validates order parameters like stock, delivery and payment
     * parameters
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket basket object
     * @param \OxidEsales\Eshop\Application\Model\User $oUser order user
     *
     * @return null
     */
    public function validateOrder($oBasket, $oUser)
    {
        if ($this->blVobapayFinishOrderReturnMode === false) {
            return parent::validateOrder($oBasket, $oUser);
        }
    }

    /**
     * Checks if payment used for current order is available and active.
     * Throws exception if not available
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket basket object
     * @param \OxidEsales\Eshop\Application\Model\User|null $oUser user object
     *
     * @return null
     */
    public function validatePayment($oBasket, $oUser = null)
    {
        if ($this->blVobapayReinitializePaymentMode === false) {
            $oReflection = new \ReflectionMethod(\OxidEsales\Eshop\Application\Model\Order::class, 'validatePayment');
            $aParams = $oReflection->getParameters();
            if (count($aParams) == 1) {
                return parent::validatePayment($oBasket); // Oxid 6.1 didnt have the $oUser parameter yet
            }

            return parent::validatePayment($oBasket, $oUser);
        }
    }

    /**
     * Checks if delivery address (billing or shipping) was not changed during checkout
     * Throws exception if not available
     *
     * @param \OxidEsales\Eshop\Application\Model\User $oUser user object
     *
     * @return int
     */
    public function validateDeliveryAddress($oUser)
    {
        if ($this->blVobapayReinitializePaymentMode === false) {
            return parent::validateDeliveryAddress($oUser);
        }
        return 0;
    }

    /**
     * Performs order cancel process
     */
    public function cancelOrder()
    {
        parent::cancelOrder();
        if ($this->vobapayIsVobapayPaymentUsed() === true) {
            $sCancelledFolder = PaymentHelper::getInstance()->getShopConfVar('vp_statuscancelled');
            if (!empty($sCancelledFolder)) {
                $this->vobapaySetFolder($sCancelledFolder);
            }
            if (!empty($this->oxorder__oxtransid->value)) {
                $oApiEndpoint = PaymentHelper::getInstance()->loadVobapayApi();
                // TODO ?
            }
        }
    }

    /**
     * Returns if order was payed with a vobapay payment type
     *
     * @return bool
     */
    public function vobapayIsVobapayPaymentUsed()
    {
        if (PaymentHelper::getInstance()->isVobapayPaymentMethod($this->oxorder__oxpaymenttype->value)) {
            return true;
        }
        return false;
    }

    /**
     * Set order folder
     *
     * @param string $sFolder
     * @return void
     */
    public function vobapaySetFolder($sFolder)
    {
        $sQuery = "UPDATE oxorder SET oxfolder = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sFolder, $this->getId()));

        $this->oxorder__oxfolder = new Field($sFolder);
    }

    /**
     * Set order trans status
     *
     * @param string $sStatus
     * @return void
     */
    public function vobapaySetTransStatus($sStatus)
    {
        $sQuery = "UPDATE oxorder SET oxtransstatus = ? WHERE oxid = ?";
        DatabaseProvider::getDb()->Execute($sQuery, array($sStatus, $this->getId()));

        $this->oxorder__oxtransstatus = new Field($sStatus);
    }

    /**
     * Returns finish payment url
     *
     * @return string|bool
     */
    public function vobapayGetPaymentFinishUrl()
    {
        return Registry::getConfig()->getSslShopUrl() . "?cl=vobapayFinishPayment&id=" . $this->getId();
    }

    /**
     * Checks if vobapay order was not finished correctly
     *
     * @return bool
     */
    public function vobapayIsOrderInUnfinishedState()
    {
        if ($this->oxorder__oxtransstatus->value == OxOrderTransStatus::NOT_FINISHED &&
            $this->oxorder__oxfolder->value == PaymentHelper::getInstance()->getShopConfVar('vp_statusprocessing')) {
            return true;
        }
        return false;
    }

    /**
     * Checks if order is elibible for finishing the payment
     *
     * @return bool
     */
    public function vobapayIsEligibleForPaymentFinish()
    {
        if (!$this->vobapayIsVobapayPaymentUsed() || $this->oxorder__oxpaid->value != '0000-00-00 00:00:00' ||
            $this->oxorder__oxtransstatus->value != OxOrderTransStatus::NOT_FINISHED) {
            return false;
        }

        $aStatus = $this->vobapayGetPaymentModel()->getTransactionHandler()->processTransaction($this, 'success');
        $aStatusBlacklist = [VpPaymentStatus::PAID];

        if (in_array($aStatus['status'], $aStatusBlacklist)) {
            return false;
        }
        return true;
    }

    /**
     * Generate vobapay payment model from paymentId
     *
     * @return Base
     */
    public function vobapayGetPaymentModel()
    {
        return PaymentHelper::getInstance()->getVobapayPaymentModel($this->oxorder__oxpaymenttype->value);
    }

    /**
     * Tries to finish an order which was paid but where the customer seemingly didn't return to the shop after payment to finish the order process
     *
     * @return integer
     */
    public function vobapayFinishOrder()
    {
        $oBasket = $this->vobapayRecreateBasket();

        $this->blVobapayFinalizeReturnMode = true;
        $this->blVobapayFinishOrderReturnMode = true;

        //finalizing order (skipping payment execution, vouchers marking and mail sending)
        return $this->finalizeOrder($oBasket, $this->getOrderUser());
    }

    /**
     * Recreates basket from order information
     *
     * @return Basket
     */
    public function vobapayRecreateBasket()
    {
        $oBasket = $this->_getOrderBasket();

        // add this order articles to virtual basket and recalculates basket
        $this->_addOrderArticlesToBasket($oBasket, $this->getOrderArticles(true));

        // recalculating basket
        $oBasket->calculateBasket(true);

        Registry::getSession()->setVariable('sess_challenge', $this->getId());
        Registry::getSession()->setVariable('paymentid', $this->oxorder__oxpaymenttype->value);
        Registry::getSession()->setBasket($oBasket);

        return $oBasket;
    }

    /**
     * This overloaded method sets the return mode flag so that the behaviour of some methods is changed when the customer
     * returns after successful payment from vobapay
     *
     * @param \OxidEsales\Eshop\Application\Model\Basket $oBasket Basket object
     * @param object $oUser Current User object
     * @param bool $blRecalculatingOrder Order recalculation
     * @return integer
     */
    public function finalizeOrder(\OxidEsales\Eshop\Application\Model\Basket $oBasket, $oUser, $blRecalculatingOrder = false)
    {
        $this->vobapayRecalculateOrder = $blRecalculatingOrder;
        if (PaymentHelper::getInstance()->isVobapayPaymentMethod($oBasket->getPaymentId()) === true &&
            $this->vobapayIsReturnAfterPayment() === true) {
            $this->blVobapayFinalizeReturnMode = true;
        }
        if (Registry::getSession()->getVariable('vobapayReinitializePaymentMode')) {
            $this->blVobapayReinitializePaymentMode = true;
        }
        return parent::finalizeOrder($oBasket, $oUser, $blRecalculatingOrder);
    }

    /**
     * Determines if the current call is a return from a redirect payment
     *
     * @return bool
     */
    protected function vobapayIsReturnAfterPayment()
    {
        if (Registry::getRequest()->getRequestEscapedParameter('fnc') == 'handleVobapayReturn') {
            return true;
        }
        return false;
    }

    /**
     * Starts a new payment with vobapay
     *
     * @return integer
     */
    public function vobapayReinitializePayment()
    {
        if ($this->oxorder__oxstorno->value == 1) {
            $this->vobapayUncancelOrder();
        }

        $oUser = $this->getUser();
        if (!$oUser) {
            $oUser = oxNew(\OxidEsales\Eshop\Application\Model\User::class);
            $oUser->load($this->oxorder__oxuserid->value);
            $this->setUser($oUser);
            Registry::getSession()->setVariable('usr', $this->oxorder__oxuserid->value);
        }

        $this->blVobapayReinitializePaymentMode = true;

        Registry::getSession()->setVariable('vobapayReinitializePaymentMode', true);

        $oBasket = Registry::getSession()->getBasket();

        /*$oPaymentModel = PaymentHelper::getInstance()->getVobapayPaymentModel(Registry::getSession()->getVariable('paymentid'));
        $oPaymentMethodRequest = $oPaymentModel->getPaymentMethodRequest();
        $oPaymentMethodRequest->addRequestParameters($oPaymentModel, $oBasket->getUser());
        $oPaymentMethod = $oPaymentMethodRequest->execute();

        if (!empty($oPaymentMethod->id)) {
            Registry::getSession()->setVariable('vobapay_current_payment_method_id', $oPaymentMethod->id);
        }*/

        return $this->finalizeOrder($oBasket, $oUser);
    }

    /**
     * Remove cancellation of the order
     *
     * @return void
     */
    public function vobapayUncancelOrder()
    {
        if ($this->oxorder__oxstorno->value == 1) {
            $this->oxorder__oxstorno = new \OxidEsales\Eshop\Core\Field(0);
            if ($this->save()) {
                // canceling ordered products
                foreach ($this->getOrderArticles() as $oOrderArticle) {
                    $oOrderArticle->vobapayUncancelOrderArticle();
                }
            }
        }
    }

    /**
     * Retrieves order id connected to given transaction id and trys to load it
     * Returns if order was found and loading was a success
     *
     * @param string $sTransactionId
     * @return bool
     * @throws \OxidEsales\Eshop\Core\Exception\DatabaseConnectionException
     */
    public function vobapayLoadOrderByTransactionId($sTransactionId)
    {
        $sQuery = "SELECT oxid FROM oxorder WHERE oxtransid = ?";

        $sOrderId = DatabaseProvider::getDb()->getOne($sQuery, array($sTransactionId));
        if (!empty($sOrderId)) {
            return $this->load($sOrderId);
        }
        return false;
    }

    /**
     * Extension: Return false in return mode
     *
     * @param string $sOxId order ID
     * @return bool
     */
    protected function _checkOrderExist($sOxId = null)
    {
        if ($this->blVobapayFinalizeReturnMode === false && $this->blVobapayReinitializePaymentMode === false) {
            return parent::_checkOrderExist($sOxId);
        }
        return false; // In finalize return situation the order will already exist, but thats ok
    }

    /**
     * Extension: In return mode load order from DB instead of generation from basket because it already exists
     *
     * @param \OxidEsales\EshopCommunity\Application\Model\Basket $oBasket Shopping basket object
     */
    protected function _loadFromBasket(\OxidEsales\Eshop\Application\Model\Basket $oBasket)
    {
        if ($this->blVobapayFinalizeReturnMode === false) {
            return parent::_loadFromBasket($oBasket);
        }
        $this->load(Registry::getSession()->getVariable('sess_challenge'));
    }

    /**
     * Extension: In return mode load existing userpayment instead of creating a new one
     *
     * @param string $sPaymentid used payment id
     * @return \OxidEsales\Eshop\Application\Model\UserPayment
     */
    protected function _setPayment($sPaymentid)
    {
        if ($this->blVobapayFinalizeReturnMode === false) {
            $mParentReturn = parent::_setPayment($sPaymentid);

            return $mParentReturn;
        }
        $oUserpayment = oxNew(\OxidEsales\Eshop\Application\Model\UserPayment::class);
        $oUserpayment->load($this->oxorder__oxpaymentid->value);
        return $oUserpayment;
    }

    /**
     * Extension: Return true in return mode since this was done in the first step
     *
     * @param \OxidEsales\EshopCommunity\Application\Model\Basket $oBasket basket object
     * @param object $oUserpayment user payment object
     * @return  integer 2 or an error code
     */
    protected function _executePayment(\OxidEsales\Eshop\Application\Model\Basket $oBasket, $oUserpayment)
    {
        if ($this->blVobapayFinalizeReturnMode === false) {
            return parent::_executePayment($oBasket, $oUserpayment);
        }

        if ($this->blVobapayReinitializePaymentMode === true) {
            // Finalize order would set a new incremented order-nr if already filled
            // Doing this to prevent this, oxordernr will be filled again in _setNumber
            $this->vobapayTmpOrderNr = $this->oxorder__oxordernr->value;
            $this->oxorder__oxordernr->value = "";
        }
        return true;
    }

    /**
     * Extension: Set pending folder for vobapay orders
     *
     * @return void
     */
    protected function _setFolder()
    {
        if (PaymentHelper::getInstance()->isVobapayPaymentMethod(Registry::getSession()->getBasket()->getPaymentId()) === false) {
            return parent::_setFolder();
        }

        if ($this->blVobapayFinalizeReturnMode === false && $this->blVobapayFinishOrderReturnMode === false) { // vobapay module has it's own folder management, so order should not be set to status NEW by oxid core
            $this->oxorder__oxfolder = new Field(PaymentHelper::getInstance()->getShopConfVar('vp_statuspending'), Field::T_RAW);
        }
    }

    /**
     * Extension: Changing the order in the backend results in da finalizeOrder call with recaltulateOrder = true
     * This sets oxtransstatus to OK, which should not happen for vobapay orders when they were not finished
     * This prevents this behaviour
     *
     * @param string $sStatus order transaction status
     */
    protected function _setOrderStatus($sStatus)
    {
        if ($this->vobapayRecalculateOrder === true &&
            $this->oxorder__oxtransstatus->value == OxOrderTransStatus::NOT_FINISHED && $this->vobapayIsVobapayPaymentUsed()) {
            return;
        }
        parent::_setOrderStatus($sStatus);
    }

    /**
     * Assigns to new oxorder object customer delivery and shipping info
     *
     * @param object $oUser user object
     */
    protected function _setUser($oUser)
    {
        parent::_setUser($oUser);
    }
}
