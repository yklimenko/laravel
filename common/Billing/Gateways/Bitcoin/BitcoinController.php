<?php 

namespace Common\Billing\Gateways\Bitcoin;

use Common\Billing\BillingPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Common\Core\BaseController;

use Blockavel\LaraBlockIo\LaraBlockIo;

class BitcoinController extends BaseController
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var BillingPlan
     */
    private $billingPlan;

    /**
     * @var Subscription
     */
    private $subscription;

    /**
     * @var BitcoinGateway
     */
    private $bitcoin;

    /**
     * @param Request $request
     * @param BillingPlan $billingPlan
     * @param Subscription $subscription
     * @param BitcoinGateway $bitcoin
     */
    public function __construct(
        Request $request,
        BillingPlan $billingPlan
    )
    {
        $this->request = $request;
        $this->billingPlan = $billingPlan;
        $this->middleware('auth', ['except' => [
            'approvedCallback', 'canceledCallback', 'loadingPopup']
        ]);
    }

    /**
     * Create subscription agreement on bitcoin.
     *
     * @return JsonResponse
     * @throws GatewayException
     */
    public function createSubscriptionAgreement()
    {
        $user = auth()->user();
        
        $plan = $this->billingPlan->findOrFail($this->request->get('plan_id'));

        $lara = new LaraBlockIo();

        $address = $lara->createAddress($user->email . "-" . $plan->amount . "-" . time());
        
        // $address = $lara->getAddressByLabel("default");
        $btc = $this->convertUSDtoBIT($plan->amount);

        if ($address->status == "success")
        {
            return $this->success([
                    'address' => $address->data->address, 
                    'label' => $address->data->label,
                    'network' => $address->data->network,
                    'user_id' => $address->data->user_id,
                    'available_balance' => $address->data->available_balance,
                    'pending_received_balance' => $address->data->pending_received_balance,
                    'amount' => $btc,
                    'plan_id' => $this->request->get('plan_id')]);
        }
        else
        {
            return $this->success(['address' => ""]);
        }
    }

    public function checkPaymentStatus()
    {
        $user = auth()->user();

        $plan = $this->billingPlan->findOrFail($this->request->get('plan_id'));

        $lara = new LaraBlockIo();
        $address = $lara->getAddressByLabel($user->email);
        // $address = $lara->getAddressByLabel("default");
        
        if ($address->status == "success" )
        {
            $btc = $this->convertUSDtoBIT($plan->amount);

            return $this->success([
                    'address' => $address->data->address, 
                    'label' => $address->data->label,
                    'network' => $address->data->network,
                    'user_id' => $address->data->user_id,
                    'available_balance' => $address->data->available_balance,
                    'pending_received_balance' => $address->data->pending_received_balance,
                    'result' => ($address->data->pending_received_balance * 1 >= $btc * 1 ? "success" : "fail"),
                    'plan_id' => $this->request->get('plan_id')]);
        }
        else
        {
            return $this->success(['address' => ""]);
        }
    }

    public function createSubscriptionAgreement1()
    {
        $this->getTestToken();
        $this->validate($this->request, [
            'plan_id' => 'required|integer|exists:billing_plans,id',
            'start_date' => 'string'
        ]);

        // Create instance of invoice
        $invoice = LaravelBitpay::Invoice();

        // Set item details (Only 1 item)
        $invoice->setItemDesc('Photo');
        $invoice->setItemCode('sku-1');
        $invoice->setPrice(1);

        // Please make sure you provide unique orderid for each invoice
        $invoice->setOrderId(1); // E.g. Your order number

        $user = auth()->user();

        // Create Buyer Instance
        $buyer = LaravelBitpay::Buyer();
        $buyer->setName($user->first_name . ' ' . $user->last_name);
        $buyer->setEmail($user->email);
        // $buyer->setAddress1('Kopargaon');
        $buyer->setNotify(true);

        // Add buyer to invoice
        $invoice->setBuyer($buyer);

        // Set currency
        $invoice->setCurrency('USD');

        // Set redirect url to get back after completing the payment. GET Request
        // $invoice->setRedirectURL(route('/'));

        // Optional config. setNotificationUrl()
        // By default, package handles webhooks and dispatches BitpayWebhookReceived event as described above.
        // If you want to handle webhooks your way, you can provide url below. 
        // If handled manually, BitpayWebhookReceived event will not be dispatched.    
        // $invoice->setNotificationUrl('Your custom POST route to handle webhooks');

        // Create invoice on bitpay server.
        $invoice = LaravelBitpay::createInvoice($invoice);

        // You can save invoice ID from server, for your your reference
        $invoiceId = $invoice->getId();

        // Redirect user to following URL for payment approval.
        $paymentUrl = $invoice->getUrl();
        
        return $this->success(['urls' => [
            'approve' => $paymentUrl,
            'execute' => $response->getCompleteUrl(),
        ]]);
    }

    private function convertUSDtoBIT($usd)
    {
        $resourceUrl = 'https://blockchain.info/tobtc?currency=USD&value=' . $usd;

        $curlCli = curl_init($resourceUrl);

        curl_setopt($curlCli, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curlCli, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curlCli);
        
        curl_close($curlCli);

        return $result;
    }

    private function getTestToken()
    {

        $privateKey = new PrivateKey('D:\bitpay.pri');
        $storageEngine = new EncryptedFilesystemStorage('Tuthk128!');
        $privateKey->generate();
        $storageEngine->persist($privateKey);
        $publicKey = $privateKey->getPublicKey();

        $sin = $publicKey->getSin()->__toString();
        


        /**
         * Use the SIN to request a pairing code and token.
         * The pairing code has to be approved in the BitPay Dashboard
         * THIS is just a cUrl example, which explains how to use the key pair for signing requests
         **/
        $resourceUrl = 'https://test.bitpay.com/tokens';

        $facade = 'merchant';

        $postData = json_encode([
            'id' => $sin,
            'facade' => $facade
        ]);

        $curlCli = curl_init($resourceUrl);

        curl_setopt($curlCli, CURLOPT_HTTPHEADER, [
            'x-accept-version: 2.0.0',
            'Content-Type: application/json',
            'x-identity' => $publicKey->__toString(),
            'x-signature' => $privateKey->sign($resourceUrl . $postData),
        ]);

        curl_setopt($curlCli, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curlCli, CURLOPT_POSTFIELDS, stripslashes($postData));
        curl_setopt($curlCli, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curlCli);
        $resultData = json_decode($result, TRUE);
        curl_close($curlCli);



        config(['laravel-bitpay.token' => $resultData['data'][0]['token']]);
    }

    /**
     * Execute subscription agreement on bitcoin.
     *
     * @return JsonResponse
     * @throws GatewayException
     */
    public function executeSubscriptionAgreement()
    {
        $this->validate($this->request, [
            'agreement_id' => 'required|string|min:1',
            'plan_id' => 'required|integer|exists:billing_plans,id',
        ]);

        $plan = $this->billingPlan->findOrFail($this->request->get('plan_id'));
        $this->request->user()->subscribe('bitcoin', $subscriptionId, $plan);

        return $this->success(['user' => $this->request->user()->load('permissions', 'subscriptions.plan')]);
    }

    /**
     * Called after user approves bitcoin payment.
     */
    public function approvedCallback()
    {
        return view('common::billing/bitcoin-popup')->with([
            'token' => $this->request->get('token'),
            'status' => 'success',
        ]);
    }

    /**
     * Called after user cancels bitcoin payment.
     */
    public function canceledCallback()
    {
        return view('common::billing/bitcoin-popup')->with([
            'token' => $this->request->get('token'),
            'status' => 'cancelled',
        ]);
    }

    /**
     * Show loading view for bitcoin.
     */
    public function loadingPopup()
    {
        return view('common::billing/bitcoin-loading-popup');
    }
}
