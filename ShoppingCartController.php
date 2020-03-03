<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddShoppingCartRequest;
use App\Http\Requests\ShoppingCartRequest;
use App\Models\ListingVariation;
use App\Services\Shop\ShoppingCartService;
use App\Services\Shop\ShoppingCartSyncService;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;

class ShoppingCartController extends Controller
{
    /**
     * @param  \App\Http\Requests\AddShoppingCartRequest  $request
     * @param $id
     * @param  \App\Services\Shop\ShoppingCartService  $cartService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxAddToCart(AddShoppingCartRequest $request, $id, ShoppingCartService $cartService)
    {
        $user      = $request->user();
        $variation = ListingVariation::findOrFail((int)$id);

        $quantity = $request->input('quantity',1);

        $cartService->add($variation, $quantity);

        return response()->json([
            'message' => 'success',
            'count_in_cart' => Cart::count(),
            'subtotal' => $cartService->subtotal(),
            'tax'      => $cartService->tax(),
            'total'    => $cartService->total(),
        ], Response::HTTP_OK);
    }

    /**
     * @param  \App\Http\Requests\ShoppingCartRequest  $request
     * @param $cart_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postUpdateToCart(ShoppingCartRequest $request, $cart_id)
    {
        $user = $request->user();

        $quantity = $request->input('quantity', 1);

        Cart::update($cart_id, $quantity);

        return Redirect::route('shop_cart');
    }

    /**
     * @param  \App\Http\Requests\ShoppingCartRequest  $request
     * @param $cart_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getRemoveFromCart(ShoppingCartRequest $request, $cart_id)
    {
        $user = $request->user();

        Cart::remove($cart_id);

        return Redirect::route('shop_cart');
    }

    /**
     * @param  \App\Http\Requests\ShoppingCartRequest  $request
     * @param $cart_id
     * @param  \App\Services\Shop\ShoppingCartService  $cartService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxRemoveFromCart(ShoppingCartRequest $request, $cart_id, ShoppingCartService $cartService)
    {
        $cartService->remove($cart_id);

        return response()->json([
            'message' => 'success',
            'count_in_cart' => Cart::count(),
            'subtotal' => $cartService->subtotal(),
            'tax'      => $cartService->tax(),
            'total'    => $cartService->total(),
        ], Response::HTTP_OK);
    }

    /**
     * @param  \App\Http\Requests\ShoppingCartRequest  $request
     * @param  \App\Services\Shop\ShoppingCartService  $cartService
     * @param  \App\Services\Shop\ShoppingCartSyncService  $syncService
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getShopCart(ShoppingCartRequest $request, ShoppingCartService $cartService, ShoppingCartSyncService $syncService)
    {
        $user = $request->user();
        $title = 'Shop cart';

        if($user) {
            $syncService->syncToCart($user->id);

            $cartService->init();
        }

        $cartService->content()->map(function ($item) {
            $item->slug     = $item->model->listing->slug;
            $item->photoUrl = optional($item->model->listing->photos->first())->url;
            return $item;
        });

        return view('pages.shop.shop_cart')->with([
            'title'    => $title,
            'user'     => $user,
            'cart'     => $cartService->content(),
            'subtotal' => $cartService->subtotal(),
            'tax'      => $cartService->tax(),
            'total'    => $cartService->total(),
        ]);
    }

    /**
     * @param  \App\Http\Requests\ShoppingCartRequest  $request
     * @param  \App\Services\Shop\ShoppingCartService  $cartService
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxShopCart(ShoppingCartRequest $request, ShoppingCartService $cartService)
    {
        $cart = $cartService->content()->map(function ($item) {
            $photoUrl = $item->model->listing->photos->first()->url;
            $item = (array)$item;
            $item['photoUrl'] = $photoUrl;
            return (object)$item;
        });

        return response()->json([
            'message'       => 'success',
            'cart'          => $cart,
            'count_in_cart' => $cartService->count(),
            'subtotal'      => $cartService->subtotal()->format(),
        ], Response::HTTP_OK);
    }
}
