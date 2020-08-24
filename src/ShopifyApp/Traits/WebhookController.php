<?php

namespace Osiset\ShopifyApp\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Osiset\ShopifyApp\Objects\Values\ShopDomain;
use Illuminate\Http\Response as ResponseResponse;

/**
 * Responsible for handling incoming webhook requests.
 */
trait WebhookController
{
    use ConfigAccessible;

    /**
     * Handles an incoming webhook.
     *
     * @param string  $type    The type of webhook
     * @param Request $request The request object.
     *
     * @return ResponseResponse
     */
    public function handle(string $type, Request $request): ResponseResponse
    {

        // Get the job class and dispatch
        $jobClass = $this->getConfig('job_namespace').str_replace('-', '', ucwords($type, '-')).'Job';
        $jobData = json_decode($request->getContent());
        if( $type == 'carts-update' || $type == 'carts-create' || $type == 'checkouts-create' || $type == 'checkouts-update' )
        {
            $jobClass::dispatch(
                new ShopDomain($request->header('x-shopify-shop-domain')),
                $jobData,
                $_SERVER['REMOTE_ADDR']
            );
        }
        else
        {
            $jobClass::dispatch(
                new ShopDomain($request->header('x-shopify-shop-domain')),
                $jobData
            );
        }

        return Response::make('', 201);
    }
}
