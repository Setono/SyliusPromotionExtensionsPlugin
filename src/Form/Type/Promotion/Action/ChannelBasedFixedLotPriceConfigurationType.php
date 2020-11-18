<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Form\Type\Promotion\Action;

use function Safe\class_alias;
use Sylius\Bundle\CoreBundle\Form\Type\ChannelCollectionType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ChannelBasedFixedLotPriceConfigurationType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entry_type' => FixedLotPriceConfigurationType::class,
            'entry_options' => function (ChannelInterface $channel): array {
                /** @var CurrencyInterface $currency */
                $currency = $channel->getBaseCurrency();

                return [
                    'label' => $channel->getName(),
                    'currency' => $currency->getCode(),
                ];
            },
        ]);
    }

    public function getParent(): string
    {
        return ChannelCollectionType::class;
    }
}

class_alias(ChannelBasedFixedLotPriceConfigurationType::class, ChannelBasedUnitsFixedDiscountConfigurationType::class);
