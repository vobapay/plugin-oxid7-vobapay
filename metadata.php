<?php

/**
 * Metadata version
 */
$sMetadataVersion = '2.1';

/**
 * Module information
 */
$aModule = [
    'id' => 'vobapay',
    'title' => 'vobapay payments',
    'description' => [
        'de' => 'Modul zur Integration aller Zahlungsmethoden von vobapay Payments.',
        'en' => 'Module to integrate all payment methods from vobapay payments.'
    ],
    'thumbnail' => 'img/vp_logo.png',
    'version' => '1.0.0',
    'author' => 'vobapay payments',
    'email' => 'integration@vobapay.de',
    'url' => 'https://www.vobapay.de',
    'extend'        => [
        \OxidEsales\Eshop\Application\Model\PaymentGateway::class => Vobapay\Payment\Extension\Model\PaymentGateway::class,
        \OxidEsales\Eshop\Application\Model\Order::class => Vobapay\Payment\Extension\Model\Order::class,
        \OxidEsales\Eshop\Application\Model\Payment::class => Vobapay\Payment\Extension\Model\Payment::class,
        \OxidEsales\Eshop\Application\Controller\PaymentController::class => Vobapay\Payment\Extension\Controller\PaymentController::class,
        \OxidEsales\Eshop\Application\Controller\OrderController::class => Vobapay\Payment\Extension\Controller\OrderController::class,
        \OxidEsales\Eshop\Application\Controller\Admin\ModuleConfiguration::class => Vobapay\Payment\Extension\Controller\Admin\ModuleConfiguration::class,
        \OxidEsales\Eshop\Application\Controller\Admin\PaymentMain::class => Vobapay\Payment\Extension\Controller\Admin\PaymentMain::class,
        \OxidEsales\Eshop\Core\Session::class => Vobapay\Payment\Extension\Core\Session::class,
    ],
    'controllers'   => [
        'vobapaywebhook' => Vobapay\Payment\Controller\VobapayWebhook::class,
        'vobapayfinishpayment' => Vobapay\Payment\Controller\VobapayFinishPayment::class,
        'vobapay_order_operations' => Vobapay\Payment\Controller\Admin\OrderOperations::class,
    ],
    'settings' => [
        ['group' => 'VOBAPAY_GENERAL', 'name' => 'vp_apiurl', 'type' => 'str', 'value' => '', 'position' => 10],
        ['group' => 'VOBAPAY_GENERAL', 'name' => 'vp_apikey', 'type' => 'str', 'value' => '', 'position' => 20],
        ['group' => 'VOBAPAY_STATUS_MAPPING', 'name' => 'vp_statuspending', 'type' => 'select', 'value' => '', 'position' => 30],
        ['group' => 'VOBAPAY_STATUS_MAPPING', 'name' => 'vp_statusprocessing', 'type' => 'select', 'value' => '', 'position' => 40],
        ['group' => 'VOBAPAY_STATUS_MAPPING', 'name' => 'vp_statuscancelled', 'type' => 'select', 'value' => '', 'position' => 50],
    ],
    'events' => [
        'onActivate' => \Vobapay\Payment\Core\Events::class.'::onActivate',
        'onDeactivate' => \Vobapay\Payment\Core\Events::class.'::onDeactivate'
    ],
];
