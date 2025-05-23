<?php

namespace Vobapay\Payment\Core\Enum;

class CaptureTypes
{
    public const AUTO = 'AUTO';
    public const MANUAL = 'MANUAL';

    public static function getAll(): array
    {
        return [
            self::AUTO,
            self::MANUAL
        ];
    }
}