<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Distributor;

interface MostExpensiveFirstDistributorInterface
{
    public function distribute(array $integers, int $amount): array;
}
