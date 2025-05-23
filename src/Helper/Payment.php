<?php

namespace Vobapay\Payment\Helper;

use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;
use OxidEsales\Eshop\Core\Registry;
use Vobapay\Payment\Core\Service\RestClient;
use Vobapay\Payment\Model\Payment\Base;

class Payment
{
    /**
     * @var Payment
     */
    protected static $oInstance = null;

    /**
     * List of all available vobapay payment methods
     *
     * @var array
     */
    protected $aPaymentMethods = array();

    /**
     * Array with information about all enabled vobapay payment types
     *
     * @var array|null
     */
    protected $aPaymentInfo = null;

    /**
     * @var ContainerInterface
     */
    protected $oContainer;

    public function __construct()
    {
        $this->aPaymentMethods = Config::getMethodsList();
    }

    /**
     * Return all available vobapay payment methods
     *
     * @return array
     */
    public function getVobapayPaymentMethods()
    {
        $aPaymentMethods = array();
        foreach ($this->aPaymentMethods as $sPaymentId => $aPaymentMethodInfo) {
            $aPaymentMethods[$sPaymentId] = $aPaymentMethodInfo['name']['de'];
        }
        return $aPaymentMethods;
    }

    /**
     * Returns payment model for given paymentId
     *
     * @param string $sPaymentId
     * @return Base
     * @throws \Exception
     */
    public function getVobapayPaymentModel($sPaymentId)
    {
        if ($this->isVobapayPaymentMethod($sPaymentId) === false || !isset($this->aPaymentMethods[$sPaymentId]['model'])) {
            throw new \Exception('vobapay Payment method unknown - ' . $sPaymentId);
        }

        $oPaymentModel = oxNew($this->aPaymentMethods[$sPaymentId]['model']);
        return $oPaymentModel;
    }

    /**
     * Determine if given paymentId is a vobapay payment method
     *
     * @param string $sPaymentId
     * @return bool
     */
    public function isVobapayPaymentMethod($sPaymentId)
    {
        return isset($this->aPaymentMethods[$sPaymentId]);
    }

    /**
     * Return vobapay api Key
     *
     * @return string
     */
    public function getVobapayApiKey()
    {
        return $this->getShopConfVar('vp_apikey');
    }

    /**
     * Returns config value
     *
     * @param string $sVarName
     * @return mixed|false
     */
    public function getShopConfVar($sVarName)
    {
        $moduleConfiguration = $this
            ->getContainer()
            ->get(ModuleConfigurationDaoBridgeInterface::class)
            ->get(Config::PLUGIN_CODE);
        if (!$moduleConfiguration->hasModuleSetting($sVarName)) {
            return false;
        }
        return $moduleConfiguration->getModuleSetting($sVarName)->getValue();
    }

    /**
     * Returns DependencyInjection container
     *
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        if ($this->oContainer === null) {
            $this->oContainer = ContainerFactory::getInstance()->getContainer();
        }
        return $this->oContainer;
    }

    /**
     * Create singleton instance of payment helper
     *
     * @return Payment
     */
    static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Collect information about all activated vobapay payment types
     *
     * @return array
     */
    public function getVobapayPaymentInfo()
    {
        if ($this->aPaymentInfo === null) {
            $aPaymentInfo = [];
            try {
                foreach ($this->aPaymentMethods as $sId => $aPaymentMethod) {
                    $aPaymentInfo[$sId] = [
                        'title' => $aPaymentMethod['name']['de'],
                        'minAmount' => 0,
                        'maxAmount' => 999999,
                    ];
                }
            } catch (\Exception $oEx) {
                Registry::getLogger()->error($oEx->getMessage());
            }
            $this->aPaymentInfo = $aPaymentInfo;
        }
        return $this->aPaymentInfo;
    }

    /**
     * Generates locale string
     * Oxid doesn't have a locale logic, so solving it with by using the language files
     *
     * @return string
     */
    public function getLocale()
    {
        $sLocale = Registry::getLang()->translateString('VOBAPAY_LOCALE');
        if (Registry::getLang()->isTranslated() === false) {
            $sLocale = 'en_US'; // default
        }
        return $sLocale;
    }

    /**
     * Returns a floating price as integer in cents
     *
     * @param float|string $fPrice
     * @return int
     */
    public function priceInCent(float|string $fPrice)
    {
        if (is_string($fPrice)) {
            $fPrice = (float)str_replace(',', '.', $fPrice);
        }

        return (int)number_format($fPrice * 100, 0, '', '');
    }

    /**
     * Converts an integer price in cents to a floating point number.
     *
     * @param int $iPrice
     * @return float
     */
    public function priceFromCent(int $iPrice)
    {
        return $iPrice / 100;
    }

    /**
     * Returns vobapay Client
     *
     * @return \Vobapay\Payment\Core\Service\RestClient
     * @throws \Exception
     */
    public function loadVobapayApi()
    {
        $container = ContainerFactory::getInstance()->getContainer();
        $restClient = $container->get(RestClient::class);

        return $restClient;
    }

    /**
     * Return the vobapay webhook url
     *
     * @return string
     */
    public function getWebhookUrl()
    {
        return Registry::getConfig()->getCurrentShopUrl() . 'index.php?cl=vobapayWebhook';
    }

    /**
     * Function to validate and correct the URL
     *
     * @param string $urlBase
     * @param string $urlResto
     * @return string
     */
    function getValidUrl(string $urlBase, string $urlResto)
    {
        // If $urlBase is empty or does not start with 'https', use API_URL_TEST
        if (empty($urlBase) || !preg_match('/^https:\/\//', $urlBase)) {
            $urlBase = Config::API_URL_TEST;
        }

        // Ensure $urlBase ends with a single slash
        $urlBase = rtrim($urlBase, '/') . 'Payment.php/';

        // Ensure $urlResto does not start with a slash
        $urlResto = ltrim($urlResto, '/');

        // Concatenate and return the final URL
        return $urlBase . $urlResto;
    }

    /**
     * Return the logo to display for the payment method.
     *
     * @param string $sPaymentId
     * @return string
     *
     */
    public function getPaymentLogo($sPaymentId)
    {
        $strLogo = '';
        $shopUrl = Registry::getConfig()->getShopUrl();

        switch ($sPaymentId) {
            case Config::PLUGIN_VP_CREDITCARD:
                $strLogo = 'vp_creditcard.png';
                break;
            case Config::PLUGIN_VP_DIRECTDEBIT:
                $strLogo = 'vp_directdebit.png';
                break;
            case Config::PLUGIN_VP_IDEAL:
                $strLogo = 'vp_ideal.png';
                break;
            case Config::PLUGIN_VP_EPS:
                $strLogo = 'vp_eps.png';
                break;
            case Config::PLUGIN_VP_PAYPAL:
                $strLogo = 'vp_paypal.png';
                break;
            case Config::PLUGIN_VP_PAYBYBANK:
                $strLogo = 'vp_paybybank.png';
                break;
            case Config::PLUGIN_VP_INVOICE:
                $strLogo = 'vp_invoice.png';
                break;
            case Config::PLUGIN_VP_INSTALLMENTS:
                $strLogo = 'vp_installments.png';
                break;
        }

        $strLogoPartialPath = 'out/modules/vobapay/img/' . $strLogo;

        if (file_exists(getShopBasePath() . 'Payment.php/' .$strLogoPartialPath)) {
            return $shopUrl . $strLogoPartialPath;
        }

        return null;
    }

    /**
     * Checks if a given text contains a sandbox URL.
     *
     * @return bool|string Returns the found sandbox URL or false if not found.
     */
    function containsSandboxUrl()
    {
        $sApiUrl = $this->getVobapayApiUrl();
        $pattern = '/https?:\/\/[^\s]*sandbox[^\s]*/i';
        if (preg_match($pattern, $sApiUrl, $matches)) {
            return $matches[0];
        }
        return false;
    }

    /**
     * Return vobapay api Url
     *
     * @return string
     */
    public function getVobapayApiUrl()
    {
        return $this->getShopConfVar('vp_apiurl');
    }
}
