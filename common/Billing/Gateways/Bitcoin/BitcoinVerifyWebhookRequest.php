<?php

namespace Common\Billing\Gateways\Bitcoin;

use Omnipay\Bitcoin\Message\AbstractRestRequest;

class BitcoinVerifyWebhookRequest extends AbstractRestRequest
{
    /**
     * Get the raw data array for this message. The format of this varies from gateway to
     * gateway, but will usually be either an associative array, or a SimpleXMLElement.
     *
     * @return mixed
     */
    public function getData()
    {
        return [];
    }

    public function getEndpoint()
    {
        return parent::getEndpoint() . '/notifications/verify-webhook-signature';
    }
}
