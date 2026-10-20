<?php

namespace AviSaaS\Connector\Classes;

final class OwnedProductCount
{
    public static function fromDocument(array $document): ?int
    {
        if (($document['identified'] ?? null) !== true) {
            return null;
        }

        $products = $document['products'] ?? null;

        if (!is_array($products)) {
            return null;
        }

        $count = 0;

        foreach ($products as $product) {
            if (is_array($product) && ($product['owned'] ?? null) === true) {
                $count++;
            }
        }

        return $count;
    }
}
