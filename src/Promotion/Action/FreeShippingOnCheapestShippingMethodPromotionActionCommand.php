<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Promotion\Action;

use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Order\Model\AdjustmentInterface as OrderAdjustmentInterface;
use Sylius\Component\Promotion\Action\PromotionActionCommandInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

final class FreeShippingOnCheapestShippingMethodPromotionActionCommand implements PromotionActionCommandInterface
{
    public const TYPE = 'free_shipping_on_cheapest_shipping_method';

    /** @var FactoryInterface */
    private $adjustmentFactory;
    /**
     * @var ShippingMethodsResolverInterface
     */
    private $shippingMethodsResolver;

    public function __construct(FactoryInterface $adjustmentFactory, ShippingMethodsResolverInterface $shippingMethodsResolver)
    {
        $this->adjustmentFactory = $adjustmentFactory;
        $this->shippingMethodsResolver = $shippingMethodsResolver;
    }

    public function execute(PromotionSubjectInterface $subject, array $configuration, PromotionInterface $promotion): bool
    {
        if (!$subject instanceof OrderInterface) {
            throw new UnexpectedTypeException($subject, OrderInterface::class);
        }

        foreach ($subject->getShipments() as $shipment) {
            $adjustment = $this->createAdjustment($promotion);

            if(!$this->shippingMethodsResolver->supports($shipment)) {
                continue;
            }

            $availableShippingMethods = $this->shippingMethodsResolver->getSupportedMethods($shipment);

            $adjustment->setAmount(-$adjustmentAmount);
            $subject->addAdjustment($adjustment);
        }



        return true;
    }

    /**
     * @throws UnexpectedTypeException
     */
    public function revert(PromotionSubjectInterface $subject, array $configuration, PromotionInterface $promotion): void
    {
        if (!$subject instanceof OrderInterface && !$subject instanceof OrderItemInterface) {
            throw new UnexpectedTypeException(
                $subject,
                'Sylius\Component\Core\Model\OrderInterface or Sylius\Component\Core\Model\OrderItemInterface'
            );
        }

        foreach ($subject->getAdjustments(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT) as $adjustment) {
            if ($promotion->getCode() === $adjustment->getOriginCode()) {
                $subject->removeAdjustment($adjustment);
            }
        }
    }

    private function createAdjustment(
        PromotionInterface $promotion,
        string $type = AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT
    ): OrderAdjustmentInterface {
        /** @var OrderAdjustmentInterface $adjustment */
        $adjustment = $this->adjustmentFactory->createNew();
        $adjustment->setType($type);
        $adjustment->setLabel($promotion->getName());
        $adjustment->setOriginCode($promotion->getCode());

        return $adjustment;
    }
}
