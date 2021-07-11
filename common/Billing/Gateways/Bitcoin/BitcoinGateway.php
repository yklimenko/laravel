<?php namespace Common\Billing\Gateways\Bitcoin;

use Common\Billing\GatewayException;
use Common\Billing\Gateways\Contracts\GatewayInterface;
use Common\Settings\Settings;
use Illuminate\Http\Request;
use Omnipay\Omnipay;
use moki74\LaravelBtc\Models\Payment;

class BitcoinGateway implements GatewayInterface
{
    /**
     * @var RestGateway
     */
    private $gateway;

    /**
     * @var BitcoinPlans
     */
    private $plans;

    /**
     * @var BitcoinSubscriptions
     */
    private $subscriptions;

    /**
     * @param Settings $settings
     */
    public function __construct(Settings $settings)
    {
        $this->gateway = app("bitcoinPayment");
        // $this->gateway->user_id = config('services.bitcoin.client_id');
        // $this->gateway->amount = 0.05;
        // $this->gateway->initialize([
        //     'clientId' => config('services.bitcoin.client_id'),
        //     'secret' => config('services.bitcoin.secret'),
        //     'testMode' => $settings->get('billing.bitcoin_test_mode'),
        // ]);

        $this->plans = new BitcoinPlans($this->gateway);
        $this->subscriptions = new BitcoinSubscriptions($this->gateway, $this->plans);
    }

    /**
     * Get bitcoin plans service instance.
     * 
     * @return BitcoinPlans
     */
    public function plans()
    {
        return $this->plans;
    }

    /**
     * Get bitcoin subscriptions service instance.
     * 
     * @return BitcoinSubscriptions
     */
    public function subscriptions()
    {
        return $this->subscriptions;
    }

    /**
     * Check if specified webhook is valid.
     *
     * @param Request $request
     * @return bool
     * @throws GatewayException
     */
    public function webhookIsValid(Request $request)
    {
        $payload = [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => config('services.bitcoin.webhook_id'),
            'webhook_event' => $request->all(),
        ];

        $response = $this->gateway->createRequest(BitcoinVerifyWebhookRequest::class)->sendData($payload);

        if ( ! $response->isSuccessful()) {
            throw new GatewayException("Could not validate bitcoin webhook: {$response->getMessage()}");
        }

        return $response->getData()['verification_status'] === 'SUCCESS';
    }
}