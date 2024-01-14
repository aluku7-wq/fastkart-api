<?php

namespace App\Models;

use App\Helpers\Helpers;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Cviebrock\EloquentSluggable\Sluggable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model implements HasMedia
{
    use Sluggable, HasApiTokens, HasFactory, SoftDeletes, Notifiable, InteractsWithMedia;

    protected $primaryKey = 'id';

    /**
     * The Products that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $fillable = [
        'name',
        'short_description',
        'description',
        'type',
        'unit',
        'quantity',
        'weight',
        'price',
        'sale_price',
        'discount',
        'sku',
        'product_thumbnail_id',
        'stock_status',
        'visible_time',
        'meta_title',
        'meta_description',
        'product_meta_image_id',
        'size_chart_image_id',
        'is_sale_enable',
        'sale_starts_at',
        'sale_expired_at',
        'is_trending',
        'is_external',
        'slug',
        'is_return',
        'is_free_shipping',
        'is_featured',
        'shipping_days',
        'is_random_related_products',
        'tax_id',
        'store_id',
        'status',
        'is_approved',
        'estimated_delivery_text',
        'return_policy_text',
        'safe_checkout',
        'secure_checkout',
        'social_share',
        'encourage_order',
        'encourage_view',
        'external_url',
        'external_button_text',
        'created_by_id'
    ];

    protected $with = [
        'variations',
        'product_thumbnail',
        'product_meta_image',
        'product_galleries',
        'attributes',
        'categories:id,name,slug,type,status',
        'tags:id,name,slug,status',
    ];

    protected $withCount = [
        'orders',
        'reviews'
    ];

    protected $appends = [
        'user_review',
        'can_review',
        'rating_count',
        'order_amount',
        'review_ratings',
        'related_products',
        'cross_sell_products',
    ];

    protected $hidden = [
        'deleted_at',
        'updated_at',
        'description',
        'cross_products',
        'similar_products',
        'meta_description',
    ];

    protected $casts = [
        'product_thumbnail_id' => 'integer',
        'size_chart_image_id' => 'integer',
        'quantity' => 'integer',
        'weight' => 'integer',
        'price' => 'float',
        'sale_price' => 'float',
        'discount' => 'integer',
        'shipping_days' => 'integer',
        'show_stock_quantity' => 'integer',
        'tax_id' => 'integer',
        'store_id' => 'integer',
        'is_cod' => 'integer',
        'is_external' => 'integer',
        'is_free_shipping' => 'integer',
        'is_featured' => 'integer',
        'is_return' => 'integer',
        'is_changeable' => 'integer',
        'is_sale_enable' => 'integer',
        'is_random_related_products' => 'integer',
        'status' => 'integer',
        'is_trending' => 'integer',
        'is_approved' => 'integer',
        'reviews_count' => 'integer',
        'rating_count' => 'float',
        'safe_checkout' => 'integer',
        'secure_checkout' => 'integer',
        'social_share' => 'integer',
        'encourage_order' => 'integer',
        'encourage_view' => 'integer',
        'cross_sell_products' => 'array'
    ];

    public static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $model->created_by_id = Helpers::getCurrentUserId();
        });
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
                'onUpdate' => true,
            ]
        ];
    }

    /**
     * @return Int
     */
    public function getId($request)
    {
        return ($request->id) ? $request->id : $request->route('product')->id;
    }

    /**
     * @return BelongsTo
     */
    public function product_thumbnail(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'product_thumbnail_id');
    }

    /**
     * @return BelongsTo
     */
    public function size_chart_image(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'size_chart_image_id');
    }

    /**
     * @return BelongsTo
     */
    public function product_meta_image(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'product_meta_image_id');
    }

    /**
     * @return BelongsTo
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * @return BelongsTo
     */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumer_id');
    }

    /**
     * @return BelongsTo
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    /**
     * @return BelongsToMany
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /**
     * @return BelongsToMany
     */
    public function product_galleries(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class, 'product_images');
    }

    /**
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }

    /**
     * @return HasMany
     */
    public function variations(): HasMany
    {
        return $this->hasMany(Variation::class, 'product_id');
    }

    /**
     * @return HasMany
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    /**
     * @return HasMany
     */
    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'product_id');
    }

    /**
     * @return HasMany
     */
    public function cart(): HasMany
    {
        return $this->hasMany(Cart::class, 'product_id');
    }

    /**
     * @return BelongsToMany
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')->with('attribute_values');
    }

    /**
     * @return BelongsToMany
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_products');
    }

    /**
     * @return BelongsToMany
     */
    public function similar_products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'related_products', 'related_product_id');
    }

    /**
     * @return BelongsToMany
     */
    public function cross_products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'cross_sell_products', 'cross_sell_product_id');
    }

    public function getRelatedProductsAttribute()
    {
        return collect($this->similar_products)->pluck('id')->all();
    }

    public function getCrossSellProductsAttribute()
    {
        return collect($this->cross_products)->pluck('id')->all();
    }

    public function getOrderAmountAttribute()
    {
        $request = app('request');
        return (float) Helpers::countOrderAmount($this->id, $request->filter_by);
    }

    public function getOrdersCountAttribute()
    {
        $request = app('request');
        return (int) Helpers::getOrderCount($this->id, $request->filter_by);
    }

    public function getReviewRatingsAttribute()
    {
        return Helpers::getReviewRatings($this->id);
    }

    public function getRatingCountAttribute()
    {
        return $this->reviews->avg('rating');
    }

    public function getCanReviewAttribute()
    {
        if (Helpers::isUserLogin()) {
            return Helpers::canReview(Helpers::getCurrentUserId(), $this->id);
        }

        return false;
    }

    public function getUserReviewAttribute()
    {
        if (Helpers::isUserLogin()) {
            return Helpers::user_review(Helpers::getCurrentUserId(), $this->id);
        }
    }
}
