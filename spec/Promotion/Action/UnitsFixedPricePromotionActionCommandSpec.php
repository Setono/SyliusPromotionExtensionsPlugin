<?php

declare(strict_types=1);

namespace spec\Setono\SyliusPromotionExtensionsPlugin\Promotion\Action;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusPromotionExtensionsPlugin\Distributor\MostExpensiveFirstDistributorInterface;
use Setono\SyliusPromotionExtensionsPlugin\Promotion\Action\UnitsFixedPricePromotionActionCommand;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\PromotionInterface;
use Sylius\Component\Core\Promotion\Filter\FilterInterface;
use Sylius\Component\Order\Model\Adjustment;
use Sylius\Component\Promotion\Action\PromotionActionCommandInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class UnitsFixedPricePromotionActionCommandSpec extends ObjectBehavior
{
    public function let(
        FactoryInterface $adjustmentFactory,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor
    ): void {
        $this->beConstructedWith($adjustmentFactory, $priceRangeFilter, $taxonFilter, $productFilter, $distributor);
    }

    public function it_is_initializable(): void
    {
        $this->shouldBeAnInstanceOf(UnitsFixedPricePromotionActionCommand::class);
    }

    public function it_implements_promotion_action_command_interface(): void
    {
        $this->shouldImplement(PromotionActionCommandInterface::class);
    }

    public function it_throws_unexpected_type_exception_if_subject_is_not_an_order(
        PromotionSubjectInterface $subject,
        PromotionInterface $promotion
    ): void {
        $this->shouldThrow('\Sylius\Component\Resource\Exception\UnexpectedTypeException')->during('execute', [$subject, [], $promotion]);
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
        FactoryInterface $adjustmentFactory,
        FilterInterface $priceRangeFilter,
        FilterInterface $taxonFilter,
        FilterInterface $productFilter,
        MostExpensiveFirstDistributorInterface $distributor
    ): void {
        $channel->getCode()->willReturn('channel_code');
        $subject->getChannel()->willReturn($channel);

        $item1 = new OrderItem();
        $item1->setUnitPrice(60);
        $unit11 = new OrderItemUnit($item1);
        $unit12 = new OrderItemUnit($item1);
        $item1->addUnit($unit11);
        $item1->addUnit($unit12);
        $item2 = new OrderItem();
        $unit21 = new OrderItemUnit($item2);
        $item2->addUnit($unit21);
        $item2->setUnitPrice(60);

        $subject->getItems()->willReturn(new ArrayCollection([$item1, $item2]));
        $priceRangeFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $taxonFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);
        $productFilter->filter([$item1, $item2], Argument::any())->willReturn([$item1, $item2]);

        $distributor->distribute([60, 60, 60], 30)->willReturn([10, 10, 10]);
        $adjustmentFactory->createNew()->willReturn(new Adjustment());

        $this->execute($subject, $this->getValidConfiguration('channel_code'), $promotion)->shouldReturn(true);
    }

    private function getValidConfiguration(string $channelCode): array
    {
        return [
            $channelCode => [
                'amount' => 100,
                'itemsAmount' => 2,
            ],
        ];
    }
}
