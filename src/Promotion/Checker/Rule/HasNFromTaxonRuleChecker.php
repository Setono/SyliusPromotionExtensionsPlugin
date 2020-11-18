<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Promotion\Checker\Rule;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Promotion\Checker\Rule\RuleCheckerInterface;
use Sylius\Component\Promotion\Exception\UnsupportedTypeException;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;

final class HasNFromTaxonRuleChecker implements RuleCheckerInterface
{
    public const TYPE = 'has_n_from_taxon';

    public function isEligible(PromotionSubjectInterface $subject, array $configuration): bool
    {
        if (!isset($configuration['taxons'], $configuration['quantity'])) {
            return false;
        }

        $quantity = $configuration['quantity'];

        if (0 === $quantity) {
            return false;
        }

        if (!$subject instanceof OrderInterface) {
            throw new UnsupportedTypeException($subject, OrderInterface::class);
        }

        $taxonCounts = [];
        foreach ($configuration['taxons'] as $taxon) {
            $taxonCounts[$taxon] = 0;
        }

        /** @var OrderItemInterface $item */
        foreach ($subject->getItems() as $item) {
            foreach ($configuration['taxons'] as $taxon) {
                if ($this->productHasTaxon($item->getProduct(), $taxon)) {
                    $taxonCounts[$taxon] += $item->getQuantity();
                }
            }
        }

        foreach ($taxonCounts as $taxonCount) {
            if ($taxonCount < $quantity) {
                return false;
            }
        }

        return true;
    }

    private function productHasTaxon(ProductInterface $product, string $taxon): bool
    {
        foreach ($product->getTaxons() as $item) {
            if ($item->getCode() === $taxon) {
                return true;
            }
        }

        return false;
    }
}
