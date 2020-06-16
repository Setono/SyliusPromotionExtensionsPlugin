<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Distributor;

use Webmozart\Assert\Assert;

final class MostExpensiveFirstDistributor implements MostExpensiveFirstDistributorInterface
{
    public function distribute(array $integers, int $amount): array
    {
        Assert::allInteger($integers);

        // Do a first round with only first item to eliminate most of non-applicable items
        $firstItemWithPromotion = ($integers[0] - $amount);
        $applicableIntegers = \array_filter($integers, function ($integer) use ($firstItemWithPromotion): bool {
            return $integer > $firstItemWithPromotion;
        });

        do {
            $totalWithPromotion = (\array_sum($applicableIntegers) - $amount) / \count($applicableIntegers);
            $previousApplicableAmount = \count($applicableIntegers);
            $applicableIntegers = \array_filter($applicableIntegers, function ($integer) use ($totalWithPromotion): bool {
                return $integer > $totalWithPromotion;
            });
            $currentApplicableAmount = \count($applicableIntegers);
        } while ($previousApplicableAmount !== $currentApplicableAmount);

        $distributedAmounts = [];
        foreach ($integers as $key => $integer) {
            if (isset($applicableIntegers[$key])) {
                $distributedAmounts[$key] = (int) \floor(\abs($totalWithPromotion - $integer));
            } else {
                $distributedAmounts[$key] = 0;
            }
        }

        $missingAmount = $amount - \abs(array_sum($distributedAmounts));
        for ($i = 0, $iMax = abs($missingAmount); $i < $iMax; ++$i) {
            $distributedAmounts[$i] += $missingAmount >= 0 ? 1 : -1;
        }

        return $distributedAmounts;
    }
}
