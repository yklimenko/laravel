<?php namespace Common\Billing\Gateways\Bitcoin;

use Common\Billing\Invoices\CrupdateInvoice;
use Common\Billing\Subscription;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Common\Billing\Gateways\GatewayFactory;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class BitcoinWebhookController extends Controller
{
    /**
     * @var BitcoinGateway
     */
    private $gateway;

    /**
     * @var Subscription
     */
    private $subscription;

    /**
     * @param GatewayFactory $gatewayFactory
     * @param Subscription $subscription
     */
    public function __construct(GatewayFactory $gatewayFactory, Subscription $subscription)
    {
        $this->gateway = $gatewayFactory->get('bitcoin');
        $this->subscription = $subscription;
    }

    /**
     * Handle a bitcoin webhook call.
     *
     * @param Request $request
     * @return Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->all();

        if ( ! $this->gateway->webhookIsValid($request)) {
            return response('Webhook validation failed', 422);
        };

        switch ($payload['event_type']) {
            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
                return $this->handleSubscriptionCancelled($payload);
            case 'PAYMENT.SALE.COMPLETED':
                return $this->handleSubscriptionRenewed($payload);
            default:
                return response('Webhook Handled', 200);
        }
    }

    /**
     * Handle a cancelled customer from a bitcoin subscription.
     *
     * @param  array  $payload
     * @return Response
     */
    protected function handleSubscriptionCancelled($payload)
    {
        $gatewayId = $payload['resource']['id'];

        $subscription = $this->subscription->where('gateway_id', $gatewayId)->first();

        if ($subscription && ! $subscription->cancelled()) {
            $subscription->markAsCancelled();
        }

        return response('Webhook Handled', 200);
    }

    /**
     * Handle a renewed stripe subscription.
     *
     * @param  array  $payload
     * @return Response
     */
    protected function handleSubscriptionRenewed($payload)
    {
        $gatewayId = Arr::get($payload, 'resource.billing_agreement_id');

        $subscription = $this->subscription->where('gateway_id', $gatewayId)->first();

        if ($subscription) {
            $bitcoinSubscription = $this->gateway->subscriptions()->find($subscription);
            $subscription->fill(['renews_at' => $bitcoinSubscription['renews_at']])->save();
            app(CrupdateInvoice::class)->execute([
                'subscription_id' => $subscription->id,
                'paid' => true,
            ]);
        }

        return response('Webhook Handled', 200);
    }
}
