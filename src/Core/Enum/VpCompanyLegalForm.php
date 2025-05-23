<?php

namespace Vobapay\Payment\Core\Enum;

class VpCompanyLegalForm
{
    public const SONSTIGE = 'Sonstige';
    public static function getAll(): array
    {
        return [
            self::SONSTIGE
        ];
    }
}