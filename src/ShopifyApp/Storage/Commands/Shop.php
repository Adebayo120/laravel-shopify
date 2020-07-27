<?php

namespace Osiset\ShopifyApp\Storage\Commands;

use App\User;
use App\ShopifyShop;
use Osiset\ShopifyApp\Contracts\ShopModel;
use Osiset\ShopifyApp\Objects\Values\ShopId;
use Osiset\ShopifyApp\Traits\ConfigAccessible;
use Osiset\ShopifyApp\Contracts\Queries\Shop as ShopQuery;
use Osiset\ShopifyApp\Contracts\Commands\Shop as ShopCommand;
use Osiset\ShopifyApp\Contracts\Objects\Values\PlanId as PlanIdValue;
use Osiset\ShopifyApp\Contracts\Objects\Values\ShopDomain as ShopDomainValue;
use Osiset\ShopifyApp\Contracts\Objects\Values\AccessToken as AccessTokenValue;

/**
 * Reprecents the commands for shops.
 */
class Shop implements ShopCommand
{
    use ConfigAccessible;

    /**
     * The shop model (configurable).
     *
     * @var ShopModel
     */
    protected $model;

    /**
     * The querier.
     *
     * @var ShopQuery
     */
    protected $query;

    /**
     * Init for shop command.
     */
    public function __construct(ShopQuery $query)
    {
        $this->query = $query;
        $this->model = $this->getConfig('user_model');
    }

    /**
     * {@inheritdoc}
     */
    public function make(ShopDomainValue $domain, AccessTokenValue $token): ShopId
    {
        if(session()->has('shop'))
        {
            if(ShopifyShop::where('user_id', session('shop'))->first())
            {
                ShopifyShop::where('user_id', session('shop'))->first()->delete();
            }
            $shop=User::find(session('shop'));
            $shop->shop_name = $domain->toNative();
            $shop->shop_password = $token->isNull() ? '' : $token->toNative();
            $shop->shop_email = "shop@{$domain->toNative()}";
            $shop->save();
            session()->forget('shop');
        }
        else
        {
            $model = $this->model;
            $shop = new $model();
            $shop->shop_name = $domain->toNative();
            $shop->shop_password = $token->isNull() ? '' : $token->toNative();
            $shop->shop_email = "shop@{$domain->toNative()}";
            $shop->save();
            session(['coming_from_shopify'=>$shop->id]);
        }

        return $shop->getId();
    }

    /**
     * {@inheritdoc}
     */
    public function setToPlan(ShopId $shopId, PlanIdValue $planId): bool
    {
        $shop = $this->getShop($shopId);
        $shop->plan_id = $planId->toNative();
        $shop->shopify_freemium = false;

        return $shop->save();
    }

    /**
     * {@inheritdoc}
     */
    public function setAccessToken(ShopId $shopId, AccessTokenValue $token): bool
    {
        $shop = $this->getShop($shopId);
        $shop->shop_password = $token->toNative();

        $shop_seperate_table= new ShopifyShop();
        $shop_seperate_table->name = $shop->shop_name;
        $shop_seperate_table->password = $token->toNative();
        $shop_seperate_table->email = $shop->shop_email;
        $shop_seperate_table->user_id = $shop->id;
        $shop_seperate_table->persisted = 0;
        $shop_seperate_table->save();

        return $shop->save(); 
    }

    /**
     * {@inheritdoc}
     */
    public function clean(ShopId $shopId): bool
    {
        $shop = $this->getShop($shopId);
        $shop->shop_password = '';
        $shop->plan_id = null;

        return $shop->save();
    }

    /**
     * {@inheritdoc}
     */
    public function softDelete(ShopId $shopId): bool
    {
        $shop = $this->getShop($shopId);
        $shop->charges()->delete();

        return $shop->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function restore(ShopId $shopId): bool
    {
        $shop = $this->getShop($shopId, true);
        $shop->charges()->restore();

        return $shop->restore();
    }

    /**
     * {@inheritdoc}
     */
    public function setAsFreemium(ShopId $shopId): bool
    {
        $shop = $this->getShop($shopId);
        $this->setAsFreemiumByRef($shop);

        return $shop->save();
    }

    /**
     * {@inheritdoc}
     */
    public function setNamespace(ShopId $shopId, string $namespace): bool
    {
        $shop = $this->getShop($shopId);
        $this->setNamespaceByRef($shop, $namespace);

        return $shop->save();
    }

    /**
     * Sets a shop as freemium.
     *
     * @param ShopModel $shop The shop model (reference).
     *
     * @return void
     */
    public function setAsFreemiumByRef(ShopModel &$shop): void
    {
        $shop->shopify_freemium = true;
    }

    /**
     * Sets a shop namespace.
     *
     * @param ShopModel $shop      The shop model (reference).
     * @param string    $namespace The namespace.
     *
     * @return void
     */
    public function setNamespaceByRef(ShopModel &$shop, string $namespace): void
    {
        $shop->shopify_namespace = $namespace;
    }

    /**
     * Helper to get the shop.
     *
     * @param ShopId $shopId      The shop's ID.
     * @param bool   $withTrashed Include trashed shops?
     *
     * @return ShopModel|null
     */
    protected function getShop(ShopId $shopId, bool $withTrashed = false): ?ShopModel
    {
        return $this->query->getById($shopId, [], $withTrashed);
    }
}
