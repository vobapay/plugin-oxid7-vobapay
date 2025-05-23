<?php

namespace Vobapay\Payment\Helper;

use OxidEsales\Eshop\Core\Registry;

/**
 * Class Config
 * Provides configuration and utility methods for the vobapay payment module.
 */
class Config
{
    // Log filename
    const LOG_FILENAME = 'vobapay.log';
    // Plugin code
    const PLUGIN_CODE = 'vobapay';
    // Plugin code text
    const PLUGIN_CODE_TXT = 'vobapay ';
    // Plugin prefix
    const PLUGIN_PREFIX = 'vp_';
    // Configuration variable name
    const VAR_CONFIG = 'vobapay_config';
    // Log level key
    const KEY_LOG_LEVEL = 'logLevel';
    // Plugin payment method identifiers
    const PLUGIN_VP_DIRECTDEBIT = 'vp_directdebit';
    const PLUGIN_VP_CREDITCARD = 'vp_creditcard';
    const PLUGIN_VP_IDEAL = 'vp_ideal';
    const PLUGIN_VP_EPS = 'vp_eps';
    const PLUGIN_VP_PAYPAL = 'vp_paypal';
    const PLUGIN_VP_PAYBYBANK = 'vp_paybybank';
    const PLUGIN_VP_INVOICE = 'vp_invoice';
    const PLUGIN_VP_INSTALLMENTS = 'vp_installments';
    const MODULE_CODE = 'module:vobapay';
    const API_URL_TEST = 'https://sandbox.api2.vobapay.de';

    /** @var \OxidEsales\Eshop\Core\Config */
    private static $config;

    /** @var VobapayLogger */
    private static $logger;

    /**
     * List of payment methods supported by the plugin.
     * @var array
     */
    private static $methods = [
        self::PLUGIN_VP_DIRECTDEBIT => [
            'name' => [
                'de' => "Lastschrift",
                'en' => "Direct Debit",
            ],
            'description' => [
                'de' => "",
                'en' => "",
            ],
            'model' => \Vobapay\Payment\Model\Payment\Directdebit::class
        ],
        self::PLUGIN_VP_CREDITCARD => [
            'name' => [
                'de' => "Kreditkarte",
                'en' => "Credit Card",
            ],
            'description' => [
                'de' => "",
                'en' => "",
            ],
            'model' => \Vobapay\Payment\Model\Payment\Creditcard::class
        ],
        self::PLUGIN_VP_IDEAL => [
            'name' => [
                'de' => "iDEAL",
                'en' => "iDEAL",
            ],
            'description' => [
                'de' => "",
                'en' => "",
            ],
            'model' => \Vobapay\Payment\Model\Payment\Ideal::class
        ],
        self::PLUGIN_VP_EPS => [
            'name' => [
                'de' => "eps",
                'en' => "eps",
            ],
            'description' => [
                'de' => "",
                'en' => "",
            ],
            'model' => \Vobapay\Payment\Model\Payment\Eps::class
        ],
        self::PLUGIN_VP_PAYPAL => [
            'name' => [
                'de' => "PayPal",
                'en' => "PayPal",
            ],
            'description' => [
                'de' => "",
                'en' => "",
            ],
            'model' => \Vobapay\Payment\Model\Payment\Paypal::class
        ],
        self::PLUGIN_VP_PAYBYBANK => [
            'name' => [
                'de' => "Open Banking",
                'en' => "Open Banking",
            ],
            'description' => [
                'de' => "",
                'en' => "",
            ],
            'model' => \Vobapay\Payment\Model\Payment\PayByBank::class
        ],
        self::PLUGIN_VP_INVOICE => [
            'name' => [
                'de' => "Rechnungskauf",
                'en' => "Invoice",
            ],
            'description' => [
                'de' => "",
                'en' => "",
            ],
            'model' => \Vobapay\Payment\Model\Payment\Invoice::class
        ],
        self::PLUGIN_VP_INSTALLMENTS => [
            'name' => [
                'de' => "Ratenkauf",
                'en' => "Installments",
            ],
            'description' => [
                'de' => "",
                'en' => "",
            ],
            'model' => \Vobapay\Payment\Model\Payment\Installments::class
        ],
    ];

    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct()
    {
        // only static context allowed
    }

    /**
     * Retrieves the list of payment methods.
     * @return array
     */
    public static function getMethodsList()
    {
        return static::$methods;
    }

    /**
     * Checks if a given payment method exists.
     * @param string $paymentId
     * @return bool
     */
    public static function methodExist($paymentId)
    {
        return array_key_exists($paymentId, static::$methods);
    }

    /**
     * Retrieves a configuration variable.
     * @param string $varName
     * @param string|null $keyName
     * @return mixed|null
     */
    public static function get($varName, $keyName = null)
    {
        static::loadConfig();

        $data = static::$config->getShopConfVar($varName);

        return $keyName ? ($data[$keyName] ?? null) : $data;
    }

    /**
     * Loads the shop configuration if not already loaded.
     */
    private static function loadConfig()
    {
        if (!static::$config) {
            static::$config = Registry::getConfig();
        }
    }

    /**
     * Retrieves the logger instance.
     * @return VobapayLogger
     */
    public static function getLogger()
    {
        if (!static::$logger) {
            static::$logger = new VobapayLogger(static::getLogFilename(), '');
        }

        return static::$logger;
    }

    /**
     * Retrieves the log filename with its full path.
     * @return string
     */
    public static function getLogFilename()
    {
        static::loadConfig();

        return Config . phpstatic::$config->getLogsDir() . static::LOG_FILENAME;
    }
}
