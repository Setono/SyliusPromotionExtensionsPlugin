<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Distributor;

use function abs;
use function array_filter;
use function array_sum;
use function array_values;
use function count;
use function floor;
use InvalidArgumentException;
use function rsort;
use Webmozart\Assert\Assert;

final class MostExpensiveFirstDistributor implements MostExpensiveFirstDistributorInterface
{
    /**
     * This will apply the amount according to the following rule :
     *
     * Take the most expensive, and apply until it equals the second most expensive one.
     * Then take the 2 most expensive, and redo the same operation until all promotion is distributed
     */
    public function distribute(array $integers, int $amount): array
    {
        Assert::allInteger($integers);
        if (!$this->areIntegersSorted($integers)) {
            throw new InvalidArgumentException('Integers must be sorted from higher to lower');
        }

        [$eligibleIntegers, $priceWithPromotion] = $this->getEligibleIntegersAndTotalWithPromotion($integers, $amount);

        // Set the array of distributed amounts being the original price - the discounted one
        $distributedAmounts = [];
        foreach ($integers as $key => $integer) {
            if (isset($eligibleIntegers[$key])) {
                $distributedAmounts[$key] = (int) floor($integer - $priceWithPromotion);
            } else {
                $distributedAmounts[$key] = 0;
            }
        }

        // Distribute the left amount that can be here if distributing 100 for 3 products ie.
        $missingAmount = $amount - abs(array_sum($distributedAmounts));
        for ($i = 0, $iMax = abs($missingAmount); $i < $iMax; ++$i) {
            $distributedAmounts[$i] += $missingAmount >= 0 ? 1 : -1;
        }

        return $distributedAmounts;
    }

    private function areIntegersSorted(array $integers): bool
    {
        $sorted = array_values($integers);
        rsort($sorted);

        return $sorted === $integers;
    }

    /**
     * This will return the array of integers that will benefit the promotion, and the amount they should worth
     */
    private function getEligibleIntegersAndTotalWithPromotion(array $integers, int $amount): array
    {
        // Do a first round with only first item to eliminate most of non-applicable items. Works only if integers are sorted
        $firstItemWithPromotion = ($integers[0] - $amount);
        $applicableIntegers = array_filter($integers, function ($integer) use ($firstItemWithPromotion): bool {
            return $integer > $firstItemWithPromotion;
        });

        do {
            $totalWithPromotion = (array_sum($applicableIntegers) - $amount) / count($applicableIntegers);
            $previousApplicableAmount = count($applicableIntegers);
            $applicableIntegers = array_filter($applicableIntegers, function ($integer) use ($totalWithPromotion): bool {
                return $integer > $totalWithPromotion;
            });
            $currentApplicableAmount = count($applicableIntegers);
        } while ($previousApplicableAmount !== $currentApplicableAmount);

        return [$applicableIntegers, $totalWithPromotion];
    }
}
