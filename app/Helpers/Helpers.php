<?php
namespace App\Helpers;

use Carbon\Carbon;
use App\Models\Cart;
use App\Models\User;
use App\Models\Order;
use App\Models\Theme;
use App\Models\Store;
use App\Models\Coupon;
use App\Models\Review;
use App\Models\Product;
use App\Models\Setting;
use App\Enums\RoleEnum;
use App\Models\Currency;
use App\Enums\OrderEnum;
use App\Models\Category;
use App\Models\Variation;
use App\Enums\SortByEnum;
use App\Enums\StockStatus;
use App\Models\Attachment;
use App\Models\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use App\Models\PaymentAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

class Helpers
{

  // Get Current User Values
  public static function isUserLogin()
  {
    return Auth::guard('api')->check();
  }

  public static function getCurrentUserId()
  {
    if (self::isUserLogin()) {
      return Auth::guard('api')->user()?->id;
    }
  }

  public static function getCurrentRoleName()
  {
    if (self::isUserLogin()) {
      return Auth::guard('api')->user()?->tokens->first()->role_type;
    }
  }

  public static function getCurrentVendorStoreId()
  {
    if (self::isUserLogin()) {
      return Auth::guard('api')->user()?->store?->id;
    }
  }

  // Attachments
  public static function createAttachment()
  {
    $attachment = new Attachment();
    $attachment->save();
    return $attachment;
  }

  public static function addMedia($model, $media, $collectionName)
  {
    return $model->addMedia($media)->toMediaCollection($collectionName);
  }

  public static function storeImage($request, $model, $collectionName)
  {
    foreach ($request as $media) {
      $attachments[] = self::addMedia($model, $media, $collectionName);
    }
    $model->forcedelete($model->id);
    return $attachments;
  }

  public static function deleteImage($model)
  {
    return $model->delete($model->id);
  }

  // Get queary base data
  public static function getSettings()
  {
    return Setting::pluck('values')->first();
  }

  public static function getAdmin()
  {
    return User::whereHas('roles', function($q) {
      $q->where('name',RoleEnum::ADMIN);
    })?->first();
  }

  public static function getAttachmentId($file_name)
  {
    return Attachment::where('file_name',$file_name)->pluck('id')->first();
  }

  public static function getRoleNameByUserId($user_id)
  {
    return User::find($user_id)?->role?->name;
  }

  public static function getCoupon($data)
  {
    return Coupon::where([['code', 'LIKE', '%'.$data.'%'],['status', true]])
	  ->orWhere('id', 'LIKE', '%'.$data.'%')
      ->with(['products', 'exclude_products'])
	  ->first();
  }

  public static function getDefaultCurrencySymbol()
  {
    $settings = self::getSettings();
    if (isset($settings['general']['default_currency'])) {
      $currency = $settings['general']['default_currency'];
      return $currency->symbol;
    }
  }

  public static function getActiveTheme()
  {
    return Theme::where('status',true)->pluck('slug');
  }

  public static function getStoreById($store_id)
  {
    return Store::where('id', $store_id)->first();
  }

  public static function getVendorIdByStoreId($store_id)
  {
    return self::getStoreById($store_id)?->vendor_id;
  }

  public static function getStoreIdByProductId($product_id)
  {
    return Product::where('id',$product_id)->pluck('store_id')->first();
  }

  public static function getProductByStoreSlug($store_slug)
  {
    return Product::whereHas('store', function (Builder $stores) use ($store_slug) {
      $stores->where('slug',$store_slug);
    });
  }

  public static function getRelatedProductId($model, $category_id, $product_id = null)
  {
    return $model->whereRelation('categories',
      function ($categories) use ($category_id) {
        $categories->Where('category_id',$category_id);
      }
    )->whereNot('id', $product_id)->inRandomOrder()->limit(6)->pluck('id')->toArray();
  }

  public static function getDefaultCurrencyCode()
  {
    $settings = Helpers::getSettings();
    $currency_id = $settings['general']['default_currency_id'];
    return Currency::whereId($currency_id)->pluck('code')->first();
  }

  public static function getCurrencyExchangeRate($currencyCode)
  {
    return Currency::where('code', $currencyCode)?->pluck('exchange_rate')?->first();
  }

  public static function convertToINR($amount)
  {
    $exchangeRate = self::getCurrencyExchangeRate('INR') ?? 1;
    $price = $amount * $exchangeRate;
    return self::roundNumber($price);
  }

  public static function getConsumerOrderByProductId($consumer_id, $product_id)
  {
    return Order::where('consumer_id',$consumer_id)->whereHas('products', function ($products) use ($product_id) {
        $products->where('product_id',$product_id);
    });
  }

  public static function getStoreWiseLastThreeProductImages($store_id)
  {
    return Product::where('store_id',$store_id)->whereNull('deleted_at')
      ->latest()->limit(3)->with('product_thumbnail')->get()
      ->pluck('product_thumbnail.original_url')
      ->toArray();
  }

  public static function roundNumber($numb)
  {
    return number_format($numb, 2, '.', '');
  }

  public static function formatDecimal($value)
  {
    return floor($value * 100) / 100;
  }

  public static function removeCart(Order $order)
  {
    $productIds = [];
    $variationIds = [];
    $cartItems = Cart::where('consumer_id',$order->consumer_id)->get();

    if ($cartItems) {
      foreach ($order->products as $product) {
        $product = $product->pivot;
        if (isset($product->variation_id)) {
          $variationIds[] = $product->variation_id;
        }

        if (isset($product->product_id)) {
          $productIds[] = $product->product_id;
        }
      }

      $cart = Cart::where('consumer_id',self::getCurrentUserId())
        ->whereIn('product_id',$productIds);

      if (!empty($variationIds)) {
        $cart = Cart::where('consumer_id',self::getCurrentUserId())
          ->whereIn('variation_id',$variationIds);
      }

      $cart->delete();
    }
  }

  public static function getProductPrice($product_id)
  {
    return Product::where('id',$product_id)->first(['price', 'discount']);
  }

  public static function getVariationPrice($variation_id)
  {
    return Variation::where('id',$variation_id)->first(['price', 'discount']);
  }

  public static function getSalePrice($product)
  {
    $productPrices = self::getPrice($product);
    return $productPrices->price - (($productPrices->price * $productPrices->discount)/100);
  }

  public static function getSubTotal($price, $quantity)
  {
    return $price * $quantity;
  }

  public static function getTotalAmount($products)
  {
    $subtotal = [];
    foreach ($products as $product) {
      $singleProductPrice = self::getSalePrice($product);
      $subtotal[] = self::getSubTotal($singleProductPrice, $product['quantity']);
    }

    return array_sum($subtotal);
  }

  public static function getPrice($product)
  {
    if (isset($product['variation_id'])) {
      return self::getVariationPrice($product['variation_id']);
    }

    return self::getProductPrice($product['product_id']);
  }

  public static function pointIsEnable()
  {
    $settings = self::getSettings();
    return $settings['activation']['point_enable'];
  }

  public static function walletIsEnable()
  {
    $settings = self::getSettings();
    return $settings['activation']['wallet_enable'];
  }

  public static function isMultiVendorEnable()
  {
    $settings = self::getSettings();
    return $settings['activation']['multivendor'];
  }

  public static function couponIsEnable()
  {
    $settings = self::getSettings();
    return $settings['activation']['coupon_enable'];
  }

  public static function getCategoryCommissionRate($categories)
  {
    return Category::whereIn('id', $categories)->pluck('commission_rate');
  }

  public static function getOrderStatusIdByName($name)
  {
    return OrderStatus::where('name',$name)->pluck('id')->first();
  }

  public static function getPaymentAccount($user_id)
  {
    return PaymentAccount::where('user_id',$user_id)->first();
  }

  public static function getTopSellingProducts($product)
  {
    $orders_count = $product->withCount(['orders'])->get()->sum('orders_count');
    $product = $product->orderByDesc('orders_count');
    if (!$orders_count) {
      $product = (new Product)->newQuery();
      $product->whereRaw('1 = 0');
      return $product;
    }

    return $product;
  }

  public static function getTopVendors($store)
  {
    $store = $store->orderByDesc('orders_count');
    $orders_count = $store->withCount(['orders'])->get()->sum('orders_count');
    if (!$orders_count) {
      $store = (new Store)->newQuery();
      $store->whereRaw('1 = 0');
      return $store;
    }

    return $store;
  }

  public static function getVariationStock($variation_id)
  {
    return Variation::where([['id', $variation_id],['stock_status', 'in_stock'],['quantity', '>', 0], ['status', true]])->first();
  }

  public static function getProductStock($product_id)
  {
    return Product::where([['id', $product_id],['stock_status', 'in_stock'], ['quantity', '>', 0], ['status', true]])->first();
  }

  public static function getCountUsedPerConsumer($consumer, $coupon)
  {
    return Order::where([['consumer_id', $consumer],['coupon_id', $coupon]])->count();
  }

  public static function getOrderByOrderNumber($order_number)
  {
    return Order::with(config('enums.order.with'))->where('order_number',$order_number)->first();
  }

  public static function decrementProductQuantity($product_id, $quantity)
  {
    $product = Product::findOrFail($product_id);
    $product->decrement('quantity', $quantity);
    $product = $product->fresh();
    if ($product->quantity <= 0) {
      $product->quantity = 0;
      self::updateProductStockStatus($product_id, StockStatus::OUT_OF_STOCK);
    }
  }

  public static function updateProductStockStatus($id, $stock_status)
  {
    return Product::where('id',$id)->update(['stock_status' => $stock_status]);
  }

  public static function incrementProductQuantity($product_id, $quantity)
  {
    $product = Product::findOrFail($product_id);
    if ($product->stock_status == StockStatus::OUT_OF_STOCK) {
      self::updateProductStockStatus($product_id, StockStatus::IN_STOCK);
    }
    $product->increment('quantity', $quantity);
  }

  public static function updateVariationStockStatus($id, $stock_status)
  {
    return Variation::findOrFail($id)->update(['stock_status' => $stock_status]);
  }

  public static function decrementVariationQuantity($variation_id, $quantity)
  {
    $variation = Variation::findOrFail($variation_id);
    $variation->decrement('quantity', $quantity);
    $variation = $variation->fresh();
    if ($variation->quantity <= 0) {
      $variation->quantity = 0;
      self::updateVariationStockStatus($variation_id, StockStatus::OUT_OF_STOCK);
    }
  }

  public static function incrementVariationQuantity($variation_id, $quantity)
  {
    $variation = Variation::findOrFail($variation_id);
    if ($variation->stock_status == StockStatus::OUT_OF_STOCK) {
      self::updateVariationStockStatus($variation_id, StockStatus::IN_STOCK);
    }
    $variation->increment('quantity', $quantity);
  }

  public static function isAlreadyReviewed($consumer_id, $product_id)
  {
    return Review::where([
      ['consumer_id', $consumer_id],
      ['product_id', $product_id]
    ])->first();
  }

  public static function countOrderAmount($product_id, $filter_by)
  {
    return self::getCompletedOrderByProductId($product_id, $filter_by)->get()->sum('total');
  }

  public static function getStoreOrderCount($store_id, $filter_by)
  {
    return self::getCompleteOrderByStoreId($store_id, $filter_by)?->get()->count();
  }

  public static function countStoreOrderAmount($store_id, $filter_by)
  {
    return self::getCompleteOrderByStoreId($store_id, $filter_by)?->sum('total');
  }

  public static function getProductCountByStoreId($store_id, $filter_by)
  {
    return self::getProductByStoreId($store_id, $filter_by)?->count();
  }

  public static function getProductByStoreId($store_id, $filter_by)
  {
    $product = Product::where('store_id', $store_id)->whereNull('deleted_at');
    return self::getFilterBy($product, $filter_by);
  }

  public static function getCompleteOrderByStoreId($store_id, $filter_by)
  {
    $order = Order::where('store_id',$store_id)->where('payment_status',PaymentStatus::COMPLETED);
    return self::getFilterBy($order, $filter_by);
  }

  public static function getFilterBy($model, $filter_by)
  {
    switch($filter_by) {
      case SortByEnum::TODAY:
        $model = $model->where('created_at', Carbon::now());
        break;

      case SortByEnum::LAST_WEEK:
        $startWeek = Carbon::now()->subWeek()->startOfWeek();
        $endWeek = Carbon::now()->subWeek()->endOfWeek();
        $model = $model->whereBetween('created_at', [$startWeek, $endWeek]);
        break;

      case SortByEnum::LAST_MONTH:
        $model = $model->whereMonth('created_at', Carbon::now()->subMonth()->month);
        break;

      case SortByEnum::THIS_YEAR:
        $model = $model->whereYear('created_at', Carbon::now()->year);
        break;
    }

    return $model;
  }

  public static function getCompletedOrderByProductId($product_id, $filter_by)
  {
    $order = Order::whereHas('products', function ($query) use($product_id) {
      $query->where('product_id',$product_id);
    })->whereNull('deleted_at')->where('payment_status',PaymentStatus::COMPLETED);

    return self::getFilterBy($order, $filter_by);
  }

  public static function getOrderCount($product_id, $filter_by)
  {
    return self::getCompletedOrderByProductId($product_id, $filter_by)?->count();
  }

  public static function isOrderCompleted($order)
  {
    if ($order->payment_status == PaymentStatus::COMPLETED &&
      $order->order_status->name == OrderEnum::DELIVERED) {
      return true;
    }

   return false;
  }

  public static function user_review($consumer_id, $product_id)
  {
    return Review::where('consumer_id',$consumer_id)
      ->where('product_id',$product_id)->whereNull('deleted_at')->first();
  }

  public static function canReview($consumer_id, $product_id)
  {
    $orders = self::getConsumerOrderByProductId($consumer_id, $product_id);
    foreach($orders as $order) {
      if (isset($order->sub_orders)) {
        if (!$order->sub_orders->isEmpty()) {
          $tempOrder = null;
          foreach($order->sub_orders as $sub_order) {
            foreach($sub_order->products as $product) {
              if ($product->id == $product_id) {
                $tempOrder = $sub_order;
              }
            }
          }

          $order = $tempOrder;
        }
      }

      if ($order) {
        if (self::isOrderCompleted($order)) {
          return true;
        }
      }
    }

    return false;
  }

  public static function getReviewRatings($product_id)
  {
    $review = Review::where('product_id', $product_id)->get();
    return [
      $review->where('rating', 1)->count(),
      $review->where('rating', 2)->count(),
      $review->where('rating', 3)->count(),
      $review->where('rating', 4)->count(),
      $review->where('rating', 5)->count(),
    ];
  }

  public static function updateProductStock(Order $order)
  {
    if ($order->payment_status == PaymentStatus::COMPLETED ||
      $order->payment_method == PaymentMethod::COD) {
      foreach ($order->products as $product) {
        $product = $product->pivot;
        if (isset($product->variation_id)) {
          self::decrementVariationQuantity($product->variation_id, $product->quantity);
        } else {
          self::decrementProductQuantity($product->product_id, $product->quantity);
        }
      }
    }
  }
}
