<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Promotion\Action;

use function count;
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

final class FixedLotPricePromotionActionCommand extends UnitDiscountPromotionActionCommand
{
    public const TYPE = 'fixed_lot_price';

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

        $channelConfiguration = $configuration[$channelCode];

        if (!isset($channelConfiguration['amount'], $channelConfiguration['itemsAmount'])) {
            return false;
        }

        $amount = (int) $channelConfiguration['amount'];
        $itemsAmount = (int) $channelConfiguration['itemsAmount'];
        if (0 === $amount || 0 === $itemsAmount) {
            return false;
        }

        $items = $this->getFilteredItems($subject, $channelConfiguration);
        if (0 === count($items)) {
            return false;
        }

        $units = $this->getUnits($items);
        $expectedTotal = $this->getExpectedTotal($amount, $itemsAmount, count($units));
        $currentTotal = $this->getEligibleItemsTotal($items);
        // If the items total is already lower than expected discounted amount, return there
        if ($currentTotal <= $expectedTotal) {
            return false;
        }

        $discountAmount = $currentTotal - $expectedTotal;
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
     * @return OrderItemInterface[]
     */
    private function getFilteredItems(OrderInterface $order, array $configuration): array
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
    private function getExpectedTotal(int $amount, int $perItems, int $actualUnitsCount): int
    {
        // Since the promotion is registered as x products for y amount, calculate price per item
        // @todo Maybe ceil than round?
        $amountPerSingleItem = (int) \round($amount / $perItems);

        // (299, 3, 4) => 299 * intval(4 / 3) + 99,67 * (4 % 3) => 299 * 1 + 99,67 * 1 = 398,67
        // (299, 3, 7) => 299 * intval(7 / 3) + 99,67 * (7 % 3) => 299 * 2 + 99,67 * 1 = 697,67
        return $amount * (int) floor($actualUnitsCount / $perItems)
            + $amountPerSingleItem * ($actualUnitsCount % $perItems)
            ;
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

        uasort($units, function (OrderItemUnitInterface $a, OrderItemUnitInterface $b): int {
            return $b->getTotal() <=> $a->getTotal();
        });
        /** @var OrderItemUnitInterface[] $units */
        $units = \array_values($units);

        return $units;
    }
}

class_alias(FixedLotPricePromotionActionCommand::class, UnitsFixedPricePromotionActionCommand::class);
