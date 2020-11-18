<?php

declare(strict_types=1);

namespace spec\Setono\SyliusPromotionExtensionsPlugin\Promotion\Action;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusPromotionExtensionsPlugin\Distributor\MostExpensiveFirstDistributor;
use Setono\SyliusPromotionExtensionsPlugin\Distributor\MostExpensiveFirstDistributorInterface;
use Setono\SyliusPromotionExtensionsPlugin\Promotion\Action\FixedLotPricePromotionActionCommand;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\PromotionInterface;
use Sylius\Component\Core\Promotion\Filter\FilterInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Promotion\Action\PromotionActionCommandInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class FixedLotPricePromotionActionCommandSpec extends ObjectBehavior
{
    public function let(
        FactoryInterface $adjustmentFactory,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor
    ): void {
        $this->beConstructedWith($adjustmentFactory, $priceRangeFilter, $taxonFilter, $productFilter, $distributor);
        $adjustmentFactory->createNew()->willReturn(new Adjustment());
    }

    public function it_is_initializable(): void
    {
        $this->shouldBeAnInstanceOf(FixedLotPricePromotionActionCommand::class);
    }

    public function it_implements_promotion_action_command_interface(): void
    {
        $this->shouldImplement(PromotionActionCommandInterface::class);
    }

    public function it_throws_unexpected_type_exception_if_subject_is_not_an_order(
        PromotionSubjectInterface $subject,
        PromotionInterface $promotion
    ): void {
        $this->shouldThrow(UnexpectedTypeException::class)->during('execute', [$subject, [], $promotion]);
    }

    public function it_does_not_apply_if_no_channel(OrderInterface $subject, PromotionInterface $promotion): void
    {
        $subject->getChannel()->willReturn(null);

        $this->execute($subject, [], $promotion)->shouldReturn(false);
    }

    public function it_does_not_apply_if_wrongly_configured(
        OrderInterface $subject,
        PromotionInterface $promotion,
        ChannelInterface $channel
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $this->execute($subject, [], $promotion)->shouldReturn(false);

        $this->execute($subject, ['channel_code' => []], $promotion)->shouldReturn(false);
    }

    public function it_does_not_apply_if_no_applicable_item(
        OrderInterface $subject,
        PromotionInterface $promotion,
        ChannelInterface $channel,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $subject->getItems()->willReturn(new ArrayCollection());
        $priceRangeFilter->filter([], Argument::any())->willReturn([]);
        $taxonFilter->filter([], Argument::any())->willReturn([]);
        $productFilter->filter([], Argument::any())->willReturn([]);

        $this->execute($subject, $this->getValidConfiguration('channel_code'), $promotion)->shouldReturn(false);
    }

    public function it_does_not_apply_if_total_is_lower_than_promotion_total(
        OrderInterface $subject,
        PromotionInterface $promotion,
        ChannelInterface $channel,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $item1 = new OrderItem();
        $unit11 = new OrderItemUnit($item1);
        $unit12 = new OrderItemUnit($item1);
        $item1->addUnit($unit11);
        $item1->addUnit($unit12);
        $item2 = new OrderItem();
        $unit21 = new OrderItemUnit($item2);
        $item2->addUnit($unit21);

        $subject->getItems()->willReturn(new ArrayCollection([$item1, $item2]));
        $priceRangeFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $taxonFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $productFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);

        $this->execute($subject, $this->getValidConfiguration('channel_code'), $promotion)->shouldReturn(false);
    }

    public function it_dispatches_promotion(
        OrderInterface $subject,
        PromotionInterface $promotion,
        ChannelInterface $channel,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $item1 = $this->getItem(60);
        $unit12 = new OrderItemUnit($item1);
        $item1->addUnit($unit12);
        $item2 = $this->getItem(60);

        $subject->getItems()->willReturn(new ArrayCollection([$item1, $item2]));
        $priceRangeFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $taxonFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $productFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);

        $realDistributor = new MostExpensiveFirstDistributor();
        $distribution = $realDistributor->distribute([60, 60, 60], 30);
        $distributor->distribute([60, 60, 60], 30)->willReturn($distribution);

        $this->execute($subject, $this->getValidConfiguration('channel_code'), $promotion)->shouldReturn(true);
    }

    public function it_tests_specific_configuration_1(
        OrderInterface $subject,
        PromotionInterface $promotion,
        ChannelInterface $channel,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $item1 = $this->getItem(81);
        $item2 = $this->getItem(30);
        $item3 = $this->getItem(55);

        $subject->getItems()->willReturn(new ArrayCollection([$item1, $item2, $item3]));
        $priceRangeFilter->filter([$item1, $item2, $item3], Argument::any())->willReturn([$item1, $item2, $item3]);
        $taxonFilter->filter([$item1, $item2, $item3], Argument::any())->willReturn([$item1, $item2, $item3]);
        $productFilter->filter([$item1, $item2, $item3], Argument::any())->willReturn([$item1, $item2, $item3]);

        $realDistributor = new MostExpensiveFirstDistributor();
        $distribution = $realDistributor->distribute([81, 55, 30], 16);
        $distributor->distribute([81, 55, 30], 16)->willReturn($distribution);

        $this->execute($subject, $this->getValidConfiguration('channel_code', 150, 3), $promotion)->shouldReturn(true);
    }

    public function it_tests_specific_configuration_2(
        OrderInterface $subject,
        PromotionInterface $promotion,
        ChannelInterface $channel,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $item1 = $this->getItem(105);
        $item2 = $this->getItem(80);

        $subject->getItems()->willReturn(new ArrayCollection([$item1, $item2]));
        $priceRangeFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $taxonFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $productFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);

        $this->execute($subject, $this->getValidConfiguration('channel_code', 200, 2), $promotion)->shouldReturn(false);
    }

    public function it_tests_specific_configuration_3(
        OrderInterface $subject,
        PromotionInterface $promotion,
        ChannelInterface $channel,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $item1 = $this->getItem(105);
        $item2 = $this->getItem(100);

        $subject->getItems()->willReturn(new ArrayCollection([$item1, $item2]));
        $priceRangeFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $taxonFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $productFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);

        $realDistributor = new MostExpensiveFirstDistributor();
        $distribution = $realDistributor->distribute([105, 100], 5);
        $distributor->distribute([105, 100], 5)->willReturn($distribution);

        $this->execute($subject, $this->getValidConfiguration('channel_code', 200, 2), $promotion)->shouldReturn(true);
    }

    public function it_tests_specific_configuration_4(
        OrderInterface $subject,
        PromotionInterface $promotion,
        ChannelInterface $channel,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $item1 = $this->getItem(105);
        $item2 = $this->getItem(100);
        $item3 = $this->getItem(120);

        $subject->getItems()->willReturn(new ArrayCollection([$item1, $item2, $item3]));
        $priceRangeFilter->filter([$item1, $item2, $item3], Argument::any())->willReturn([$item1, $item2, $item3]);
        $taxonFilter->filter([$item1, $item2, $item3], Argument::any())->willReturn([$item1, $item2, $item3]);
        $productFilter->filter([$item1, $item2, $item3], Argument::any())->willReturn([$item1, $item2, $item3]);

        $realDistributor = new MostExpensiveFirstDistributor();
        $distribution = $realDistributor->distribute([120, 105, 100], 25);
        $distributor->distribute([120, 105, 100], 25)->willReturn($distribution);

        $this->execute($subject, $this->getValidConfiguration('channel_code', 200, 2), $promotion)->shouldReturn(true);
    }

    private function getValidConfiguration(
        string $channelCode,
        int $amount = null,
        int $itemsAmount = null
    ): array {
        return [
            $channelCode => [
                'amount' => $amount ?? 100,
                'itemsAmount' => $itemsAmount ?? 2,
            ],
        ];
    }

    private function getItem(int $amount): OrderItem
    {
        $item = new OrderItem();
        $item->setUnitPrice($amount);
        $unit = new OrderItemUnit($item);
        $item->addUnit($unit);

        return $item;
    }
}
