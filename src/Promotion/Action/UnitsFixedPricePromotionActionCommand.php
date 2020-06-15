<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Promotion\Action;

use Doctrine\Common\Collections\Collection;
use Setono\SyliusPromotionExtensionsPlugin\Distributor\MostExpensiveFirstDistributorInterface;
use Sylius\Component\Core\Distributor\ProportionalIntegerDistributorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Promotion\Action\UnitDiscountPromotionActionCommand;
use Sylius\Component\Core\Promotion\Applicator\UnitsPromotionAdjustmentsApplicatorInterface;
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

    /** @var UnitsPromotionAdjustmentsApplicatorInterface */
    private $unitsPromotionAdjustmentsApplicator;

    public function __construct(
        FactoryInterface $adjustmentFactory,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor,
        UnitsPromotionAdjustmentsApplicatorInterface $unitsPromotionAdjustmentsApplicator
    ) {
        parent::__construct($adjustmentFactory);

        $this->priceRangeFilter = $priceRangeFilter;
        $this->taxonFilter = $taxonFilter;
        $this->productFilter = $productFilter;
        $this->distributor = $distributor;
        $this->unitsPromotionAdjustmentsApplicator = $unitsPromotionAdjustmentsApplicator;
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

        $amount = $configuration[$channelCode]['amount'];
        if (0 === $amount) {
            return false;
        }
        $itemsAmount = $configuration[$channelCode]['itemsAmount'];
        if (0 === $itemsAmount) {
            return false;
        }

        $filteredItems = $this->getFilteredItems($subject, $configuration[$channelCode]);
        if (empty($filteredItems)) {
            return false;
        }

        $amount = $this->getExpectedTotal($amount, $itemsAmount, $filteredItems);
        // If the items total is already lower than expected discounted amount, return there
        $currentTotal = $this->getEligibleItemsTotal($filteredItems);
        dump($amount, $currentTotal);
        if ($currentTotal <= $amount) {
            return false;
        }

        $discountAmount = $currentTotal - $amount;
        $units = $this->getUnits($filteredItems);
        $unitsTotals = [];
        foreach ($units as $unit) {
            $unitsTotals[] = $unit->getTotal();
        }

        dump($units);
        dump($unitsTotals);
        $splitPromotion = $this->distributor->distribute($unitsTotals, $discountAmount);
        dump($splitPromotion);

        foreach ($units as $key => $unit) {
            $this->setUnitAdjustments($unit, $splitPromotion[$key], $promotion);
        }

        dump($subject);

        return true;
    }

    private function setUnitAdjustments(OrderItemUnitInterface $unit, int $amount, PromotionInterface $promotion): void
    {
        $this->addAdjustmentToUnit(
            $unit,
            min($unit->getTotal(), $amount),
            $promotion
        );
    }

    /**
     * @param OrderInterface $order
     * @param array          $configuration
     *
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

    private function getExpectedTotal(int $expectedAmount, int $itemsAmount, iterable $items): int
    {
        // Since the promotion is registered as x products for y amount, calculate price per item
        $amountPerItem = (int)\round($expectedAmount / $itemsAmount);

        // Return the price per item * items amount
        $units = [];
        /** @var OrderItemInterface $item */
        foreach ($items as $item) {
            $units = \array_merge($units, $item->getUnits()->toArray());
        }
        return $amountPerItem * \count($units);
    }

    private function getEligibleItemsTotal(iterable $eligibleItems): int
    {
        $itemsTotal = 0;
        /** @var OrderItemInterface $item */
        foreach ($eligibleItems as $item) {
            $itemsTotal += $item->getTotal();
        }

        return $itemsTotal;
    }

    /**
     * @param array $items
     *
     * @return array|OrderItemUnitInterface[]
     */
    private function getUnits(array $items): array
    {
        $units = [];
        /** @var OrderItemInterface $item */
        foreach ($items as $item) {
            $units = \array_merge($units, $item->getUnits()->toArray());
        }

        \uasort($units, function (OrderItemUnitInterface $a, OrderItemUnitInterface $b): int {
            return $b->getTotal() <=> $a->getTotal();
        });
        $units = \array_values($units);

        return $units;
    }
}
