<?php

namespace API;

use API\Definition\Endpoint;
use Illuminate\Pagination\Paginator;

class DynamoBuilder extends \BaoPham\DynamoDb\DynamoDbQueryBuilder
{
    public function items()
    {
        return $this->get();
    }
    
    public function toArray()
    {
        $items = $this->items();
        
        return $items->toArray();
    }
    
    public function paginate($perPage, $total = null, Endpoint $endpoint)
    {
        $this->take($perPage);
        $items = $this->items();
        $itemsArray = $items->toArray();
        
        //$items = !$items ? $items : $items;
        
        $api = app()->get(API::class);
        
        $paginator = new Paginator($itemsArray, $perPage);
        
        $paginator->setPath(env('APP_URL') . $api->base->endpoint);
        
        $paginator->hasMorePagesWhen($total > count($itemsArray));
        if ($total > count($itemsArray)) {
        }
    
        $lastKey = null;
        if ($itemsArray && ($last = $items->last())) {
            $lastKey = $last->setKeyName($endpoint->getIdentifier());
            $paginator->appends([
                'last_key' => $lastKey->getKey()
            ]);
        }
        
        return $paginator;
    }
}