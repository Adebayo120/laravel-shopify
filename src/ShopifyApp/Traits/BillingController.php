<?php

namespace Osiset\ShopifyApp\Traits;

use App\SmsCredit;
use App\SmsCreditHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Osiset\ShopifyApp\Actions\GetPlanUrl;
use Osiset\ShopifyApp\Actions\ActivatePlan;
use Osiset\ShopifyApp\Services\ShopSession;
use Osiset\ShopifyApp\Objects\Values\PlanId;
use Illuminate\Contracts\View\View as ViewView;
use Osiset\ShopifyApp\Actions\ActivateUsageCharge;
use Osiset\ShopifyApp\Objects\Values\NullablePlanId;
use Osiset\ShopifyApp\Http\Requests\StoreUsageCharge;
use Osiset\ShopifyApp\Objects\Values\ChargeReference;
use Osiset\ShopifyApp\Objects\Transfers\UsageChargeDetails as UsageChargeDetailsTransfer;

/**
 * Responsible for billing a shop for plans and usage charges.
 */
trait BillingController
{
    /**
     * Redirects to billing screen for Shopify.
     *
     * @param int|null    $plan        The plan's ID, if provided in route.
     * @param GetPlanUrl  $getPlanUrl  The action for getting the plan URL.
     * @param ShopSession $shopSession The shop session helper.
     *
     * @return ViewView
     */
    public function index(?int $plan = null, GetPlanUrl $getPlanUrl, ShopSession $shopSession): ViewView
    {
        // Get the plan URL for redirect
        $url = $getPlanUrl(
            $shopSession->getShop()->getId(),
            NullablePlanId::fromNative($plan)
        );

        // Do a fullpage redirect
        return View::make(
            'shopify-app::billing.fullpage_redirect',
            ['url' => $url]
        );
    }

    /**
     * Processes the response from the customer.
     *
     * @param int          $plan         The plan's ID.
     * @param Request      $request      The HTTP request object.
     * @param ActivatePlan $activatePlan The action for activating the plan for a shop.
     * @param ShopSession  $shopSession The shop session helper.
     *
     * @return RedirectResponse
     */
    public function process(
        int $plan,
        Request $request,
        ActivatePlan $activatePlan,
        ShopSession $shopSession
    ): RedirectResponse {
        // Activate the plan and save
        $result = $activatePlan(
            $shopSession->getShop()->getId(),
            new PlanId($plan),
            new ChargeReference($request->query('charge_id'))
        );

        // Go to homepage of app
        return Redirect::to('settings#/billing')->with(
            $result ? 'status' : 'error',
            $result ? 'Plan Upgraded Successfully' : ' Oops An Error Occured'
        );
    }

    /**
     * Allows for setting a usage charge.
     *
     * @param StoreUsageCharge    $request             The verified request.
     * @param ActivateUsageCharge $activateUsageCharge The action for activating a usage charge.
     * @param ShopSession         $shopSession         The shop session helper.
     *
     * @return RedirectResponse
     */
    public function usageCharge(
        StoreUsageCharge $request,
        ActivateUsageCharge $activateUsageCharge,
        ShopSession $shopSession
    ): RedirectResponse {
        $validated = $request->validated();

        // Create the transfer object
        $ucd = new UsageChargeDetailsTransfer();
        $ucd->price = $validated['price'];
        $ucd->description = $validated['description'];

        // Activate and save the usage charge
        $activateUsageCharge(
            $shopSession->getShop()->getId(),
            $ucd
        );

        $this->update_software_on_successfull_credit_purchase();

        // All done, return with success
        return isset($validated['redirect']) ?
            Redirect::to($validated['redirect'])->with('status', 'Sms Credit Purchase was Successfully') :
            Redirect::back()->with('status', 'Sms Credit Purchase was Successfully');
    }

    public function update_software_on_successfull_credit_purchase ()
    {
        $data = session('data');
        $user = session('user');
        // store sms credit history
        $credit_history= new SmsCreditHistory();
        $credit_history->credit = $data['sms_credit'];
        $credit_history->amount = $data['sms_credit_amount'];
        $credit_history->user_id = $user->id;
        $credit_history->save();
        // Check if user already has credit
        $availableCredit = SmsCredit::where( 'user_id', '=' ,$user->id )->first();
        if ($availableCredit) {
            $availableCredit->static_credit = $data['sms_credit'];
            $availableCredit->decreasable_credit = $availableCredit->decreasable_credit + $data['sms_credit'];
            $availableCredit->static_amount = $availableCredit->decreasable_amount + $data['sms_credit_amount'];
            $availableCredit->decreasable_amount = $availableCredit->decreasable_amount + $data['sms_credit_amount'];
            $availableCredit->save();
        }else {
            // User hasn't purchased SMS Credit Before
            $smsCreditLoad = new SmsCredit();
            $smsCreditLoad->static_credit = $data['sms_credit'];
            $smsCreditLoad->decreasable_credit = $data['sms_credit'];
            $smsCreditLoad->static_amount = $data['sms_credit_amount'];
            $smsCreditLoad->decreasable_amount = $data['sms_credit_amount'];
            $smsCreditLoad->user_id = $user->id;
            $smsCreditLoad->save();
        }
        $user->exhaust_sms_credit= 0;
        $user->save();
        session()->forget('data');
        session()->forget('user');
    }
}
