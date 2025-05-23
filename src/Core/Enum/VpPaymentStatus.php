<?php

namespace Vobapay\Payment\Core\Enum;

class VpPaymentStatus
{
    public const OPEN = 'OPEN';
    public const PENDING = 'PENDING';
    public const REDIRECT = 'REDIRECT';
    public const CANCELED = 'CANCELED';
    public const AUTHORIZED = 'AUTHORIZED';
    public const EXPIRED = 'EXPIRED';
    public const FAILED = 'FAILED';
    public const PAID = 'PAID';
    public const REFUNDED = 'REFUNDED';
    public const PARTIAL_REFUNDED = 'PARTIAL_REFUNDED';
}
