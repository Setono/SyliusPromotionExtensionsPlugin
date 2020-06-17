<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Promotion\Action;

use Setono\SyliusPromotionExtensionsPlugin\Distributor\MostExpensiveFirstDistributorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Promotion\Action\UnitDiscountPromotionActionCommand;
use Sylius\Component\Core\Promotion\Filter\FilterInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class UnitsFixedPricePromotionActionCommand extends UnitDiscountPromotionActionCommand
{
    public const TYPE = 'units_fixed_price';

    /** @var FilterInterface */
    private $priceRangeFilter;

    /** @var FilterInterface */
    private $taxonFilter;

    /** @var FilterInterface */
    private $productFilter;

    /** @var MostExpensiveFirstDistributorInterface */
    private $distributor;

    public function __construct(
        FactoryInterface $adjustmentFactory,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor
    ) {
        parent::__construct($adjustmentFactory);

        $this->priceRangeFilter = $priceRangeFilter;
        $this->taxonFilter = $taxonFilter;
        $this->productFilter = $productFilter;
        $this->distributor = $distributor;
    }

    public function execute(PromotionSubjectInterface $subject, array $configuration, PromotionInterface $promotion): bool
    {
        if (!$subject instanceof OrderInterface) {
            throw new UnexpectedTypeException($subject, OrderInterface::class);
        }

        $channel = $subject->getChannel();
        if (null === $channel) {
            return false;
        }

        $channelCode = $channel->getCode();
        if (!isset($configuration[$channelCode])) {
            return false;
        }

        if (empty($configuration[$channelCode]['amount']) || empty($configuration[$channelCode]['itemsAmount'])) {
            return false;
        }
        $amount = $configuration[$channelCode]['amount'];
        $itemsAmount = $configuration[$channelCode]['itemsAmount'];

        $filteredItems = $this->getFilteredItems($subject, $configuration[$channelCode]);
        if (empty($filteredItems)) {
            return false;
        }

        $expectedTotal = $this->getExpectedTotal($amount, $itemsAmount, $filteredItems);
        $currentTotal = $this->getEligibleItemsTotal($filteredItems);
        // If the items total is already lower than expected discounted amount, return there
        if ($currentTotal <= $expectedTotal) {
            return false;
        }

        $discountAmount = $currentTotal - $expectedTotal;
        $units = $this->getUnits($filteredItems);
        $unitsTotals = [];
        foreach ($units as $unit) {
            $unitsTotals[] = $unit->getTotal();
        }

        $splitPromotion = $this->distributor->distribute($unitsTotals, $discountAmount);

        foreach ($units as $key => $unit) {
            $this->addAdjustmentToUnit($unit, $splitPromotion[$key], $promotion);
        }

        return true;
    }

    /**
     * @return iterable|OrderItemInterface[]
     */
    private function getFilteredItems(OrderInterface $order, array $configuration): iterable
    {
        $filteredItems = $this->priceRangeFilter->filter(
            $order->getItems()->toArray(),
            array_merge(['channel' => $order->getChannel()], $configuration)
        );
        $filteredItems = $this->taxonFilter->filter($filteredItems, $configuration);

        return $this->productFilter->filter($filteredItems, $configuration);
    }

    /**
     * Returns the expected total for items. If we have 2 for $200, and 6 item units, we expect $600 as a result here
     */
    private function getExpectedTotal(int $expectedAmount, int $itemsAmount, iterable $items): int
    {
        // Since the promotion is registered as x products for y amount, calculate price per item
        $amountPerItem = (int) \round($expectedAmount / $itemsAmount);

        // Return the price per item * items amount
        $units = [];
        /** @var OrderItemInterface $item */
        foreach ($items as $item) {
            $units = \array_merge($units, $item->getUnits()->toArray());
        }

        return $amountPerItem * \count($units);
    }

    /**
     * @param iterable|OrderItemInterface[] $eligibleItems
     */
    private function getEligibleItemsTotal(iterable $eligibleItems): int
    {
        $itemsTotal = 0;
        foreach ($eligibleItems as $item) {
            $itemsTotal += $item->getTotal();
        }

        return $itemsTotal;
    }

    /**
     * @param iterable|OrderItemInterface[] $items
     *
     * @return array|OrderItemUnitInterface[]
     */
    private function getUnits(iterable $items): array
    {
        $units = [];
        foreach ($items as $item) {
            $units = \array_merge($units, $item->getUnits()->toArray());
        }

        \uasort($units, function (OrderItemUnitInterface $a, OrderItemUnitInterface $b): int {
            return $b->getTotal() <=> $a->getTotal();
        });
        /** @var OrderItemUnitInterface[] $units */
        $units = \array_values($units);

        return $units;
    }
}
