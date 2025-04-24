# ShopBundle
Shop Bundle for eCommerce with Symfony

**BUNDLE UNDER DEVELOPMENT, USE AT YOUR OWN RISKS**

With is bundle, you'll be able to manage a shop + crowdfunding.

## Installation

First, launch `composer require c975l/shop-bundle` to install the bundle.

Create database tables : `php bin/console make:migration` and `php bin/console doctrine:migrations:migrate`.

Crreate a `private` at your root level and add it to your `.gitignore` file.

## Configuration

The bundle relies on the use of `App\Entity\User`. If you haven't, create it with `php bin/console make:user`. Then add one and give it `ROLE_ADMIN`. You can use `php bin/console security:hash-password` to hash the password.

Create a login form/logout route: `php bin/console make:security:form-login`. Then adapt to your needs.

You may launch `php bin/console doctrine:schema:validate` to check missing relations and them to your User class.

Add the following configuration in the different files:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            logout:
                path: app_logout
    access_control:
        - { path: ^/shop/management, roles: ROLE_ADMIN }
```

```yaml
# config/packages/vich_uploader.yaml
vich_uploader:
    db_driver: orm

    metadata:
        type: attribute

    mappings:
        products:
            uri_prefix: '' # path added in ShopMediaNamer
            upload_destination: '%kernel.project_dir%/public/medias/shop/products'
            namer: c975L\ShopBundle\Namer\ShopMediaNamer
            inject_on_load: false
            delete_on_update: true
            delete_on_remove: true
        productItems:
            uri_prefix: '' # path added in ShopMediaNamer
            upload_destination: '%kernel.project_dir%/public/medias/shop/items'
            namer: c975L\ShopBundle\Namer\ShopMediaNamer
            inject_on_load: false
            delete_on_update: true
            delete_on_remove: true
        crowdfundings:
            uri_prefix: '' # path added in ShopMediaNamer
            upload_destination: '%kernel.project_dir%/public/medias/shop/crowdfundings'
            namer: c975L\ShopBundle\Namer\ShopMediaNamer
            inject_on_load: false
            delete_on_update: true
            delete_on_remove: true
        crowdfundingsCounterparts:
            uri_prefix: '' # path added in ShopMediaNamer
            upload_destination: '%kernel.project_dir%/public/medias/shop/counterparts'
            namer: c975L\ShopBundle\Namer\ShopMediaNamer
            inject_on_load: false
            delete_on_update: true
            delete_on_remove: true
```


```yaml
# config/routes.yaml
c975_l_shop:
    resource: "@c975LShopBundle/"
    type:     attribute
    prefix:   /
```

## Configure the webhook in Stripe dashboard

Create a Stripe account if not: https://stripe.com

1. Sign in to your [Stripe Dashboard](https://dashboard.stripe.com/)
2. Navigate to Developers > Webhooks
3. Click "Add endpoint"
4. Enter your webhook URL (https://your-website.com/shop/stripe/webhook)
5. Select the event `checkout.session.completed`
6. Copy the webhook signing secret and add it to your environment variables `config/config_bundles.yaml` -> `stripeWebhookSecret`
7. Test the endpoint to ensure proper configuration

This webhook allows Stripe to notify your application when payments are completed, ensuring order processing even if customers close their browser after payment.

Create the configuration file `config/config_bundles.yaml` with these settings:

```yaml
c975LShop:
    name: 'My Shop' # Name of the shop
    roleNeeded: 'ROLE_ADMIN' # Role needed to access shop management
    from: 'contactp@example.com' # Email address for sending emails
    fromName: 'My Shop' # Sender name
    replyTo: 'contact@example.com' # Reply-to email address
    replyToName: 'My Shop' # Reply-to name
    currency: 'eur' # ISO currency code
    shipping: 500 # Shipping cost in cents (5.00)
    shippingFree: 10000 # Free shipping threshold (100.00)
    sitemapBaseUrl: 'https://example.com'  # Base URL for sitemap
    stripeSecret: 'STRIPE_SECRET' # Stripe secret key
    stripeWebhookSecret: 'STRIPE_WEBHOOK_SECRET' # Stripe webhook secret
    touUrl: 'https://example.com/terms-of-use' # Terms of use URL
    tosUrl: 'https://example.com/terms-of-sales' # Terms of sales URL
```

Then launch configuration process `php bin/console config:create`.

## Stimulus vcontrollers

In `assets/bootstrap.js`, add the following code:

```javascript
import c975lShopBasket from '/bundles/c975lshop/js/basket.js';
import c975lShopLottery from '/bundles/c975lshop/js/lottery.js';

app.register("basket", c975lShopBasket);
app.register("lottery", c975lShopLottery);
```

## CSP

Adapts your CSP to allow:

`script-src: 'unsafe-inline'` et `form-action '*'`

## CSS

Add `@import url("/bundles/c975lshop/css/styles.min.css");` to your `assets/app.css` file.

## Urls

You should be able to use your shop + Management with the following urls:

- Shop https://example.com/shop
- Management: https://example.com/shop/management

## Useful Commands

The `basket.contentflags` has multiples values depending on its content.

The `basket.status` are the following: new, validated, paid, downloaded, shipped, finished

Run this Command `php bin/console shop:downloads:delete` once a day to delete files made available at download.

Run this Command `php bin/console shop:products:position` once a day to correct position and keep a 5 gap between them.

Run this Command `php bin/console shop:baskets:delete` once a day to delete unvalidated baskets (status new and creation > 14 days).

Run this Command `php bin/console shop:media:delete` once a day to remove physical ProdutItemMedia not deleted when the ProductItem is deleted (see TODO below)

For creating the sitemap, you can run `php bin/console shop:sitemaps:create` thath will give a `public/sitemap-shop.xml` that you can add to your `sitemap-index.xml` file or run the following:

```php
    //Creates the sitemap for pages managed by Shop
    public function createSitemapShop($output)
    {
        $command = $this->getApplication()->find('shop:sitemaps:create');
        $inputArray = new ArrayInput([]);
        $command->run($inputArray, $output);
    }
```

## Entities structure and nesting

- Product [Collection]
  - ProductMedia [Collection]
  - ProductItem [Collection]
    - ProductItemMedia [One]
    - ProductItemFile [One]

- Crowdfunding [Collection]
  - CrowdfundingMedia [Collection]
  - CrowdfundingVideo [Collection]
  - CrowdfundingCounterpart [Collection]
    - CrowdfundingCounterpartMedia [One]
  - CrowdfundingNews [Collection]
  - CrowdfundingContributor [Collection]
    - CrowdfundingContributorCounterpart [Collection]

- Lottery [Collection]
  - LotteryPrize [Collection]
  - LotteryTickets [Collection]

## TODO

In `src/Listener/ProductItemListener.php` we need to create an empty `ProductItemMedia|ProductItemFile` if none is added, otherwise we can't add one afterwards. The physical ProdutItemMedia is not deleted when the ProductItem is deleted, but the link is removed. See `ProductItemListener->prePersist()`. Furthermore, need to create ProductItem without Meida/File first.
