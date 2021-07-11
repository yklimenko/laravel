<?php namespace Common\Billing\Gateways\Bitcoin;

use Common\Billing\BillingPlan;
use Omnipay\Bitcoin\RestGateway;
use Common\Billing\GatewayException;
use Common\Billing\Gateways\Contracts\GatewayPlansInterface;

class BitcoinPlans implements GatewayPlansInterface
{
    /**
     * @var RestGateway
     */
    private $gateway;

    /**
     * BitcoinPlans constructor.
     *
     * @param RestGateway $gateway
     */
    public function __construct(RestGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Find specified plan on bitcoin.
     *
     * @param BillingPlan $plan
     * @param int $page
     * @return array|null
     */
    public function find(BillingPlan $plan, $page = 0)
    {
        if ($plan->bitcoin_id) {
            $response = $this->gateway
                ->createRequest(BitcoinFetchPlanRequest::class)
                ->setPlanId($plan->bitcoin_id)
                ->send(['planId' => $plan->bitcoin_id]);

            $bitcoinPlan = $response->getData();

            if (empty($bitcoinPlan) || ! $response->isSuccessful()) {
                return null;
            } else {
                return $bitcoinPlan;
            }
        }

        // legacy, before bitcoin plan ID was stored on billing plan model

        $response = $this->gateway->listPlan(
            ['pageSize' => 20, 'page' => $page, 'totalRequired' => 'yes', 'status' => RestGateway::BILLING_PLAN_STATE_ACTIVE]
        )->send();

        // there are no plans created on bitcoin at all
        if ( ! isset($response->getData()['plans'])) return null;

        // match plan by UUID stored in description
        $bitcoinPlan = collect($response->getData()['plans'])->first(function ($bitcoinPlan) use ($plan) {
            return $bitcoinPlan['description'] === $plan->uuid;
        });

        // found a match
        if ($bitcoinPlan) return $bitcoinPlan;

        // if there are more plans to paginate, do a recursive loop
        if ($page < (int) $response->getData()['total_pages']) {
            return $this->find($plan, $page + 1);
        }

        // count not find matching plan
        return null;
    }

    /**
     * Get specified plan's Bitcoin ID.
     *
     * @param BillingPlan $plan
     * @return string
     * @throws GatewayException
     */
    public function getPlanId(BillingPlan $plan)
    {
        if ($plan->bitcoin_id) {
            return $plan->bitcoin_id;
        }

        // legacy, before bitcoin plan ID was stored on billing plan model
        if ( ! $bitcoinPlan = $this->find($plan)) {
            throw new GatewayException("Could not find plan '{$plan->name}' on bitcoin. Try to sync plans from 'admin -> plans' page.");
        }

        return $bitcoinPlan['id'];
    }

    /**
     * Create a new subscription plan on bitcoin.
     *
     * @param BillingPlan $plan
     * @throws GatewayException
     * @return bool
     */
    public function create(BillingPlan $plan)
    {
        $response = $this->gateway->createPlan([
            'name'  => $plan->name,
            'description'  => $plan->uuid,
            'type' => RestGateway::BILLING_PLAN_TYPE_INFINITE,
            'paymentDefinitions' => [
                [
                    'name'               => $plan->name.' definition',
                    'type'               => RestGateway::PAYMENT_REGULAR,
                    'frequency'          => strtoupper($plan->interval),
                    'frequency_interval' => $plan->interval_count,
                    'cycles'             => 0,
                    'amount'             => ['value' => number_format($plan->amount, 2), 'currency' => strtoupper($plan->currency)], // bitcoin does not accept floats, need to convert amount to string
                ],
            ],
            'merchant_preferences' => [
                'return_url' => url('billing/bitcoin/callback/approved'),
                'cancel_url' => url('billing/bitcoin/callback/canceled'),
                'auto_bill_amount' => 'YES',
                'initial_fail_amount_action' => 'CONTINUE',
                'max_fail_attempts' => '3',
            ]
        ])->send();

        if ( ! $response->isSuccessful()) {
            throw new GatewayException($response->getMessage());
        }

        $bitcoinId = $response->getData()['id'];

        //set plan to active on bitcoin
        $response = $this->gateway->updatePlan([
            'state' => RestGateway::BILLING_PLAN_STATE_ACTIVE,
            'transactionReference' => $bitcoinId,
        ])->send();

        if ( ! $response->isSuccessful()) {
            throw new GatewayException($response->getMessage());
        }

        $plan->fill(['bitcoin_id' => $bitcoinId])->save();

        return true;
    }

    /**
     * Delete specified billing plan from currently active gateway.
     *
     * @param BillingPlan $plan
     * @return bool
     * @throws GatewayException
     */
    public function delete(BillingPlan $plan)
    {
        return $this->gateway->updatePlan([
            'transactionReference' => $this->getPlanId($plan),
            'state' => RestGateway::BILLING_PLAN_STATE_DELETED
        ])->send()->isSuccessful();
    }
}