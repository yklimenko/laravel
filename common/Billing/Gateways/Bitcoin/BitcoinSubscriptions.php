<?php namespace Common\Billing\Gateways\Bitcoin;

use Common\Billing\BillingPlan;
use Common\Billing\GatewayException;
use Common\Billing\Gateways\Contracts\GatewaySubscriptionsInterface;
use Common\Billing\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Omnipay\Bitcoin\RestGateway;
use App\User;

class BitcoinSubscriptions implements GatewaySubscriptionsInterface
{
    /**
     * @var RestGateway
     */
    private $gateway;

    /**
     * @var BitcoinPlans
     */
    private $bitcoinPlans;

    /**
     * BitcoinPlans constructor.
     * @param RestGateway $gateway
     * @param BitcoinPlans $bitcoinPlans
     */
    public function __construct(RestGateway $gateway, BitcoinPlans $bitcoinPlans)
    {
        $this->gateway = $gateway;
        $this->bitcoinPlans = $bitcoinPlans;
    }

    /**
     * Fetch specified subscription's details from bitcoin.
     *
     * @param Subscription $subscription
     * @return array
     * @throws GatewayException
     */
    public function find(Subscription $subscription)
    {
        $response = $this->gateway->createRequest(BitcoinFetchBillingAgreementRequest::class, [
            'transactionReference' => $subscription->gateway_id
        ])->send();

        if ( ! $response->isSuccessful()) {
            throw new GatewayException("Could not find bitcoin subscription: {$response->getMessage()}");
        }

        return [
            'renews_at' => Carbon::parse($response->getData()['agreement_details']['next_billing_date']),
        ];
    }

    /**
     * Create subscription agreement on bitcoin.
     *
     * @param BillingPlan $plan
     * @param User $user
     * @param string|null $startDate
     * @return array
     * @throws GatewayException
     */
    public function create(BillingPlan $plan, User $user, $startDate = null)
    {
        $response = $this->gateway->createSubscription([
            'name'        => config('app.name')." subscription: {$plan->name}.",
            'description' => "{$plan->name} subscription on ".config('app.name'),
            'planId' => $this->bitcoinPlans->getPlanId($plan),
            'startDate' => $startDate ? Carbon::parse($startDate) : Carbon::now()->addMinute(),
            'payerDetails' => ['payment_method' => 'bitcoin'],
        ])->send();

        if ( ! $response->isSuccessful() || ! $response->isRedirect()) {
            $message = $response->getMessage();
            throw new GatewayException("Could not create subscription agreement on bitcoin: $message");
        }

        if ($this->gateway->getTestMode()) {
            $uri = 'https://www.sandbox.bitcoin.com';
        } else {
            $uri = 'https://www.bitcoin.com';
        }

        return [
            'approve' => "$uri/checkoutnow?version=4&token={$response->getTransactionReference()}",
            'execute' => $response->getCompleteUrl(),
        ];
    }

    /**
     * Immediately cancel subscription agreement on bitcoin.
     *
     * @param Subscription $subscription
     * @param bool $atPeriodEnd
     * @return bool
     * @throws GatewayException
     */
    public function cancel(Subscription $subscription, $atPeriodEnd = false)
    {
        $response = $this->gateway->suspendSubscription([
            'transactionReference' => $subscription->gateway_id,
            'description' => 'Cancelled by user.'
        ])->send();

        if ( ! $response->isSuccessful()) {
            throw new GatewayException("Bitcoin sub cancel failed: {$response->getMessage()}");
        }

        return true;
    }

    /**
     * Resume specified subscription on bitcoin.
     *
     * @param Subscription $subscription
     * @param array $params
     * @return bool
     * @throws GatewayException
     */
    public function resume(Subscription $subscription, $params)
    {
        $response = $this->gateway->reactivateSubscription([
            'transactionReference' => $subscription->gateway_id,
            'description' => 'Resumed by user.'
        ])->send();

        if ( ! $response->isSuccessful()) {
            throw new GatewayException("Bitcoin sub resume failed: {$response->getMessage()}");
        }

        return true;
    }

    /**
     * Change billing plan of specified subscription.
     *
     * @param Subscription $subscription
     * @param BillingPlan $newPlan
     * @return array
     */
    public function changePlan(Subscription $subscription, BillingPlan $newPlan)
    {
        //TODO: implement when bitcoin fully supports billing agreement plan change. In the meantime
        // it's done on the front-end by cancelling user subscription and then creating a new one.

        return [];
    }

    /**
     * Execute bitcoin subscription agreement.
     *
     * @param string $agreementId
     * @return string
     * @throws GatewayException
     */
    public function executeAgreement($agreementId)
    {
        $response = $this->gateway->completeSubscription([
            'transactionReference' => $agreementId
        ])->send();

        if ( ! $response->isSuccessful()) {
            throw new GatewayException("Bitcoin sub agreement execute failed: {$response->getMessage()}");
        }

        return $response->getTransactionReference();
    }
}
