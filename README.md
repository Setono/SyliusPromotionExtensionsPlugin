# Sylius Promotion Extensions Plugin

[![Latest Version][ico-version]][link-packagist]
[![Latest Unstable Version][ico-unstable-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]

Adds common promotion rules and actions for you to use in Sylius.

## Installation

### Step 1: Download the plugin

```bash
$ composer require setono/sylius-promotion-extensions-plugin
```

### Step 2: Enable the plugin

Then, enable the plugin by adding it to the list of registered plugins/bundles
in the `config/bundles.php` file of your project:

```php
<?php

return [
    // ...
    
    Setono\SyliusPromotionExtensionsPlugin\SetonoSyliusPromotionExtensionsPlugin::class => ['all' => true],
    
    // ...
];
```

## Promotion rule checkers
### Has at least n from taxons
Will return true if the cart contains n or more products that have the given taxons.

## Promotion actions
### Unit fixed price
You set a price that all matching products will cost no matter their original price.

### Fixed lot price
This is specially suited for 'x for y' promotions, i.e. '2 for $50'. You set a lot price (i.e. $50) and a lot size (i.e. 2)
and then the promotion will distribute the discount among the eligible products in the cart.

[ico-version]: https://poser.pugx.org/setono/sylius-promotion-extensions-plugin/v/stable
[ico-unstable-version]: https://poser.pugx.org/setono/sylius-promotion-extensions-plugin/v/unstable
[ico-license]: https://poser.pugx.org/setono/sylius-promotion-extensions-plugin/license
[ico-github-actions]: https://github.com/Setono/SyliusPromotionExtensionsPlugin/workflows/build/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-promotion-extensions-plugin
[link-github-actions]: https://github.com/Setono/SyliusPromotionExtensionsPlugin/actions
