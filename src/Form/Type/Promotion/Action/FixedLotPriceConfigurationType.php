<?php

declare(strict_types=1);

namespace Setono\SyliusPromotionExtensionsPlugin\Form\Type\Promotion\Action;

use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionFilterCollectionType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

final class FixedLotPriceConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'setono_sylius_promotion_extensions.form.promotion_action.fixed_lot_price_configuration.price_per_lot',
                'constraints' => [
                    new NotBlank(['groups' => ['sylius']]),
                    new Type(['type' => 'numeric', 'groups' => ['sylius']]),
                ],
                'currency' => $options['currency'],
            ])
            ->add('itemsAmount', IntegerType::class, [
                'label' => 'setono_sylius_promotion_extensions.form.promotion_action.fixed_lot_price_configuration.lot_size',
                'constraints' => [
                    new NotBlank(['groups' => ['sylius']]),
                    new Type(['type' => 'numeric', 'groups' => ['sylius']]),
                ],
            ])
            ->add('filters', PromotionFilterCollectionType::class, [
                'label' => false,
                'required' => false,
                'currency' => $options['currency'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('currency')
            ->setAllowedTypes('currency', 'string')
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_promotion_extensions_promotion_action_fixed_lot_price_configuration';
    }
}
