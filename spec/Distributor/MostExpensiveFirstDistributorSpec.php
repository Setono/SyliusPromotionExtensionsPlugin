<?php

declare(strict_types=1);

namespace spec\Setono\SyliusPromotionExtensionsPlugin\Distributor;

use PhpSpec\ObjectBehavior;
use Setono\SyliusPromotionExtensionsPlugin\Distributor\MostExpensiveFirstDistributor;
use Setono\SyliusPromotionExtensionsPlugin\Distributor\MostExpensiveFirstDistributorInterface;

final class MostExpensiveFirstDistributorSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldBeAnInstanceOf(MostExpensiveFirstDistributor::class);
    }

    public function it_implements_most_expensive_first_distributor_interface(): void
    {
        $this->shouldImplement(MostExpensiveFirstDistributorInterface::class);
    }

    public function it_distributes_amount(): void
    {
        $integers = [
            262,
            240,
            220,
        ];
        $amount = 50;

        $result = $this->distribute($integers, $amount);
        $result[0]->shouldEqual(36);
        $result[1]->shouldEqual(14);
        $result[2]->shouldEqual(0);

        $integers = [
            262,
            252,
            242,
            101,
        ];
        $amount = 100;
        $result = $this->distribute($integers, $amount);
        $result[0]->shouldEqual(44);
        $result[1]->shouldEqual(33);
        $result[2]->shouldEqual(23);
        $result[3]->shouldEqual(0);

        $integers = [
            81,
            55,
            30,
        ];
        $amount = 16;
        $result = $this->distribute($integers, $amount);
        $result[0]->shouldEqual(16);
        $result[1]->shouldEqual(0);
        $result[2]->shouldEqual(0);
    }
}
