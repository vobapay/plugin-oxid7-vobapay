<?php

namespace Vobapay\Payment\Extension\Core;

class Session extends Session_parent
{
    /**
     * Returns configuration array with info which parameters require session
     * start
     *
     * @return array
     */
    protected function _getRequireSessionWithParams()
    {
        $this->_aRequireSessionWithParams['cl']['vobapayFinishPayment'] = true;

        return parent::_getRequireSessionWithParams();
    }
}
