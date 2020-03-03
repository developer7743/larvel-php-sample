<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutPaymentRequest;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\CreateOrderCheckoutRequest;
use App\Http\Requests\EditAddressCheckoutRequest;
use App\Services\Shop\OrderService;
use App\Services\Shop\ShippingService;
use Gloudemans\Shoppingcart\Cart;
use Illuminate\Support\Facades\Redirect;

class CheckoutController extends Controller
{

    /**
     * @param CheckoutRequest $request
     * @param Cart $cart
     * @param OrderService $orderService
     * @param ShippingService $shippingService
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function getCheckout(
        CheckoutRequest $request,
        Cart $cart,
        OrderService $orderService,
        ShippingService $shippingService
    ) {
        if(app('cart')->instance('default')->content()->isEmpty()) {
            return Redirect::route('shop_cart');
        }
        $user = $request->user()->load('address');
        if (empty($user->address) or ($user->address->country == 'CA' && empty($user->address->state))) {
            return Redirect::route('delivery_address');
        }

        $checkouts = $orderService->CartStructuring($cart, $request);

        return view('pages.shop.checkout')->with([
            'title'    => 'Checkout',
            'user'     => $user,
            'address'  => $user->address,
            'products' => $checkouts,
        ]);
    }

    /**
     * @param CreateOrderCheckoutRequest $request
     * @param Cart $cart
     * @param OrderService $orderService
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function getCheckoutSuccess(CheckoutPaymentRequest $request, OrderService $orderService)
    {
        $checkoutSession = $orderService->paymentSuccess($request);

        $order = $checkoutSession->orders->first() ?? null;

        if (!empty($checkoutSession) && $checkoutSession->isPaid()) {

            $template = 'pages.shop.checkout_success';
        } else {

            $template = 'pages.shop.checkout_fails';
        }

        return view($template)->with(
            [
                'email'        => $order->email ?? '',
                'count_orders' => $checkoutSession->orders->count() ?? 0,
                'name'         => $order->shop->shopProfile->business_name ?? '',
            ]
        );
    }

    /**
     * @param CheckoutRequest $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getDeliveryAddress(CheckoutRequest $request)
    {
        $user = $request->user();

        return view('pages.shop.delivery_address')->with([
            'title'   => 'Delivery address',
            'user'    => $user,
            'address' => $user->address,
        ]);
    }

    /**
     * @param EditAddressCheckoutRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postDeliveryAddress(EditAddressCheckoutRequest $request)
    {

        $user = $request->user()->load('address');
        if (empty($user->address)) {
            $address          = $user->address()->create(
                $request->get('address')
            );
            $user->address_id = $address->id;
            $user->save();
        } else {
            $user->address->fill($request->get('address'));
            $user->address->save();
        }

        return Redirect::route('checkout');
    }

    /**
     * Process payment with express checkout
     *
     * @param  \App\Http\Requests\CreateOrderCheckoutRequest  $request
     * @param  \Gloudemans\Shoppingcart\Cart  $cart
     * @param  \App\Services\Shop\OrderService  $orderService
     * @param  \App\Services\Paypal\PaypalPaymentService  $paymentService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processCheckout(CreateOrderCheckoutRequest $request, Cart $cart, OrderService $orderService)
    {
        if(app('cart')->instance('default')->content()->isEmpty()) {
            return response()->json(["message" => 'Shopping cart is empty'], 404);
        }

        try {
            $payment = $orderService->CartProcessing($request, $cart);
        } catch (\Exception $ex) {
            return response()->json([
                "message" => $ex->getMessage(),
            ], 400);
        }

        return response()->json(['approval_url' => $payment->getApprovalLink()], 200);
    }
}
