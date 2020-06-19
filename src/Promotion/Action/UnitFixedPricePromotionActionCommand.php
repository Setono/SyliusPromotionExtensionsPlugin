<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Promotion\Action;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Promotion\Action\UnitDiscountPromotionActionCommand;
use Sylius\Component\Core\Promotion\Filter\FilterInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class UnitFixedPricePromotionActionCommand extends UnitDiscountPromotionActionCommand
{
    public const TYPE = 'unit_fixed_price';

    /** @var FilterInterface */
    private $priceRangeFilter;

    /** @var FilterInterface */
    private $taxonFilter;

    /** @var FilterInterface */
    private $productFilter;

    public function __construct(
        FactoryInterface $adjustmentFactory,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter
    ) {
        parent::__construct($adjustmentFactory);

        $this->priceRangeFilter = $priceRangeFilter;
        $this->taxonFilter = $taxonFilter;
        $this->productFilter = $productFilter;
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

        $filteredItems = $this->priceRangeFilter->filter(
            $subject->getItems()->toArray(),
            array_merge(['channel' => $subject->getChannel()], $configuration[$channelCode])
        );
        $filteredItems = $this->taxonFilter->filter($filteredItems, $configuration[$channelCode]);
        $filteredItems = $this->productFilter->filter($filteredItems, $configuration[$channelCode]);

        if (count($filteredItems) === 0) {
            return false;
        }

        foreach ($filteredItems as $item) {
            $this->setUnitsAdjustments($item, $amount, $promotion);
        }

        return true;
    }

    private function setUnitsAdjustments(OrderItemInterface $item, int $amount, PromotionInterface $promotion): void
    {
        $amount = $item->getUnitPrice() - $amount;

        /** @var OrderItemUnitInterface $unit */
        foreach ($item->getUnits() as $unit) {
            $this->addAdjustmentToUnit(
                $unit,
                min($unit->getTotal(), $amount),
                $promotion
            );
        }
    }
}
