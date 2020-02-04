# Sylius Promotion Extensions Plugin

[![Latest Version][ico-version]][link-packagist]
[![Latest Unstable Version][ico-unstable-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Quality Score][ico-code-quality]][link-code-quality]

Adds common promotion rules and actions for you to use in Sylius.

## Installation

### Step 1: Download the plugin

Open a command console, enter your project directory and execute the following command to download the latest stable version of this plugin:

```bash
$ composer require setono/sylius-promotion-extensions-plugin
```

This command requires you to have Composer installed globally, as explained in the [installation chapter](https://getcomposer.org/doc/00-intro.md) of the Composer documentation.


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

[ico-version]: https://poser.pugx.org/setono/sylius-promotion-extensions-plugin/v/stable
[ico-unstable-version]: https://poser.pugx.org/setono/sylius-promotion-extensions-plugin/v/unstable
[ico-license]: https://poser.pugx.org/setono/sylius-promotion-extensions-plugin/license
[ico-github-actions]: https://github.com/Setono/SyliusPromotionExtensionsPlugin/workflows/build/badge.svg
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Setono/SyliusPromotionExtensionsPlugin.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/setono/sylius-promotion-extensions-plugin
[link-github-actions]: https://github.com/Setono/SyliusPromotionExtensionsPlugin/actions
[link-code-quality]: https://scrutinizer-ci.com/g/Setono/SyliusPromotionExtensionsPlugin
