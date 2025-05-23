<?php

namespace Vobapay\Payment\Model\Request;

use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Application\Model\Article;
use OxidEsales\Eshop\Application\Model\OrderArticle;
use OxidEsales\Eshop\Core\Price;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Helper\Payment as PaymentHelper;

abstract class Base
{
    /**
     * Array or request parameters
     *
     * @var array
     */
    protected $aParameters = [];

    /**
     * Add parameter to request
     *
     * @param string $sKey
     * @param string|array $mValue
     * @return void
     */
    public function addParameter($sKey, $mValue)
    {
        $this->aParameters[$sKey] = $mValue;
    }

    /**
     * Execute Request to vobapay API and return Response
     *
     * @return mixed
     * @throws \Exception
     */
    public abstract function execute();

    /**
     * Add all different types of basket items to the basketline array
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    public function getBasketItems(CoreOrder $oOrder)
    {
        $aItems = [];

        $sCurrency = $oOrder->oxorder__oxcurrency->value;
        $oPaymentHelper = PaymentHelper::getInstance();

        $aOrderArticleList = $oOrder->getOrderArticles();

        foreach ($aOrderArticleList->getArray() as $oOrderarticle) {
            $oArticle = $oOrderarticle->getArticle();
            if ($oArticle instanceof OrderArticle) {
                $oArticle = oxNew(Article::class);
                $oArticle->load($oOrderarticle->oxorderarticles__oxartid->value);
            }

            $sPrice = $oOrderarticle->oxorderarticles__oxbprice->value;
            $sNetPrice = $oOrderarticle->oxorderarticles__oxnprice->value;
            $sTaxPrice = $sPrice - $sNetPrice;

            $aItems[] = [
                'name' => $oOrderarticle->oxorderarticles__oxtitle->value,
                'description' => $oOrderarticle->oxorderarticles__oxshortdesc->value,
                'quantity' => (string)$oOrderarticle->oxorderarticles__oxamount->value,
                'unit_price' =>
                    [
                        'value' => $oPaymentHelper->priceInCent($sPrice),
                        'value_net' => $oPaymentHelper->priceInCent($sNetPrice),
                        'value_tax' => $oPaymentHelper->priceInCent($sTaxPrice),
                        'currency' => $sCurrency
                    ],
                'total_amount' =>
                    [
                        'value' => $oPaymentHelper->priceInCent($oOrderarticle->oxorderarticles__oxbrutprice->value),
                        'tax_rate' => $oOrderarticle->oxorderarticles__oxvat->value,
                        'currency' => $sCurrency
                    ],
            ];
        }

        return $aItems;
    }

    /**
     * Get amount array
     *
     * @param CoreOrder $oOrder
     * @param double $dAmount
     * @return array
     */
    protected function getAmountParameters(CoreOrder $oOrder, $dAmount)
    {
        $oPaymentHelper = PaymentHelper::getInstance();
        return [
            'currency' => $oOrder->oxorder__oxcurrency->value,
            'value' => $oPaymentHelper->priceInCent($dAmount),
        ];
    }

    /**
     * Return billing address parameters from Order object
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    protected function getBillingAddressParameters(CoreOrder $oOrder)
    {
        $aReturn = [
            'first_name' => $oOrder->oxorder__oxbillfname->value,
            'last_name' => $oOrder->oxorder__oxbilllname->value,
            'street' => $oOrder->oxorder__oxbillstreet->value,
            'housenumber' => $oOrder->oxorder__oxbillstreetnr->value,
            'postal_code' => $oOrder->oxorder__oxbillzip->value,
            'city' => $oOrder->oxorder__oxbillcity->value,
            'country' => $this->getCountryCode($oOrder->oxorder__oxbillcountryid->value),
        ];

        if (!empty((string)$oOrder->oxorder__oxbillemail->value)) {
            $aReturn['email'] = $oOrder->oxorder__oxbillemail->value;
        }

        return $aReturn;
    }

    /**
     * Loads country object and return country iso code
     *
     * @param string $sCountryId
     * @return string
     */
    protected function getCountryCode($sCountryId)
    {
        $oCountry = oxNew('oxcountry');
        $oCountry->load($sCountryId);
        return $oCountry->oxcountry__oxisoalpha2->value;
    }

    /**
     * Convert region id into region title
     *
     * @param string $sRegionId
     * @return string
     */
    protected function getRegionTitle($sRegionId)
    {
        $oState = oxNew('oxState');
        return $oState->getTitleById($sRegionId);
    }

    /**
     * Return customer parameters from User object
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    protected function getCustomerParameters(CoreOrder $oOrder)
    {
        $oUser = $oOrder->getUser();

        $aReturn = [
            'first_name' => $oUser->oxuser__oxfname->value,
            'last_name' => $oUser->oxuser__oxlname->value,
            'resource' => 'personal'
        ];

        if ($oUser && $oUser->oxuser__oxbirthdate->value != '0000-00-00') {
            $aReturn['date_of_birth'] = $oUser->oxuser__oxbirthdate->value;
        } else {
            $aReturn['date_of_birth'] = '2006-09-08';
        }

        if (!empty((string)$oUser->oxuser__oxfon->value)) {
            $aReturn['phone'] = $oUser->oxuser__oxfon->value;
        }

        if (!empty((string)$oUser->oxuser__oxusername->value)) {
            $aReturn['email'] = $oUser->oxuser__oxusername->value;
        }

        return $aReturn;
    }

    /**
     * Return shipping address parameters
     *
     * @param CoreOrder $oOrder
     * @return array
     */
    protected function getShippingAddressParameters(CoreOrder $oOrder)
    {
        $aReturn = [
            'first_name' => $oOrder->oxorder__oxdelfname->value,
            'last_name' => $oOrder->oxorder__oxdellname->value,
            'street' => trim($oOrder->oxorder__oxdelstreet->value),
            'housenumber' => trim($oOrder->oxorder__oxdelstreetnr->value),
            'postal_code' => $oOrder->oxorder__oxdelzip->value,
            'city' => $oOrder->oxorder__oxdelcity->value,
            'country' => $this->getCountryCode($oOrder->oxorder__oxdelcountryid->value),
        ];

        if (!empty((string)$oOrder->oxorder__oxbillemail->value)) {
            $aReturn['email'] = $oOrder->oxorder__oxbillemail->value;
        }

        return $aReturn;
    }

    /**
     * Returns collected request parameters
     *
     * @return array
     */
    protected function getParameters()
    {
        return $this->aParameters;
    }
}
