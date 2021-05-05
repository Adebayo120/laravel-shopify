<?php

namespace Osiset\ShopifyApp\Traits;

use Carbon\Carbon;
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
        if ( $type == "carts-update" || $type == "carts-create" )
        {
            // Get the job class and dispatch
            $jobClass = $this->getConfig('job_namespace').str_replace('-', '', ucwords($type, '-')).'Job';
            $jobData = json_decode($request->getContent());
            $jobClass::dispatch(
                new ShopDomain($request->header('x-shopify-shop-domain')),
                $jobData
            )->onQueue('shopify')
            ->delay( Carbon::now()->addSeconds(2) );
        }
        else
        {
            // Get the job class and dispatch
            $jobClass = $this->getConfig('job_namespace').str_replace('-', '', ucwords($type, '-')).'Job';
            $jobData = json_decode($request->getContent());
            $jobClass::dispatch(
                new ShopDomain($request->header('x-shopify-shop-domain')),
                $jobData
            )->onQueue('shopify');
        }

        return Response::make('', 201);
    }
}
