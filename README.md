# ShopBundle
Shop Bundle for eCommerce with Symfony

**BUNDLE UNDER DEVELOPMENT, USE AT YOUR OWN RISKS**

The bundle relies on the use of `App\Entity\User`.

In `config/packages/security.yaml` add the following configuration:

```yaml
    access_control:
        - { path: ^/shop/management, roles: ROLE_ADMIN }
```

In `config/packages/vich_uploader.yaml` add the following configuration:

```yaml
vich_uploader:
    db_driver: orm

    metadata:
        type: attribute

    mappings:
        products:
            uri_prefix: '' # path added in Listener
            upload_destination: '%kernel.project_dir%/public/medias/shop/products'
            namer: Vich\UploaderBundle\Naming\UniqidNamer
            inject_on_load: false
            delete_on_update: true
            delete_on_remove: true
        productItems:
            uri_prefix: '' # path added in Listener
            upload_destination: '%kernel.project_dir%/public/medias/shop/items'
            namer: Vich\UploaderBundle\Naming\UniqidNamer
            inject_on_load: false
            delete_on_update: true
            delete_on_remove: true
        productItemsFiles:
            uri_prefix: '' # path added in Listener
            upload_destination: '%kernel.project_dir%/private/medias/shop/items' # Has to be outside of public folder, otherwise accessible to anyone, and added in .gitignore
            namer: Vich\UploaderBundle\Naming\UniqidNamer
            inject_on_load: false
            delete_on_update: true
            delete_on_remove: true
```

The `basket.digital` has 3 values: 1 (digital), 2 (both) and 3 (physical).

The `basket.status` are the following: new, validated, paid, downloaded, delivered, finished

Run this Command `php bin/console shop:downloads:delete` once a day to delete files made available at download.

Run this Command `php bin/console shop:products:position` once a day to correct position and keep a 5 gap between them.

Run this Command `php bin/console shop:baskets:delete` once a day to delete unvalidated baskets (status new and creation > 14 days).

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

Create the configuration file `config/packages/c975l_shop.yaml` with these settings:

```yaml
c975l_shop:
    roleNeeded: 'ROLE_ADMIN'  # Role needed to access shop management
    from: 'shop@example.com'  # Email address for sending emails
    fromName: 'My Shop'       # Sender name
    replyTo: 'contact@example.com'
    replyToName: 'Customer Service'
    currency: 'EUR'           # ISO currency code
    shipping: 500             # Shipping cost in cents (5.00)
    shippingFree: 10000       # Free shipping threshold (100.00)
    sitemapBaseUrl: 'https://example.com'  # Base URL for sitemap
```

```yaml
# config/config_bundles.yaml
c975LShop:
    stripeSecret: 'STRIPE_SECRET'
    stripeWebhookSecret: 'STRIPE_WEBHOOK_SECRET'
```

## Configure the webhook in Stripe dashboard

1. Sign in to your [Stripe Dashboard](https://dashboard.stripe.com/)
2. Navigate to Developers > Webhooks
3. Click "Add endpoint"
4. Enter your webhook URL (https://your-website.com/shop/stripe/webhook)
5. Select the event `checkout.session.completed`
6. Copy the webhook signing secret and add it to your environment variables
7. Test the endpoint to ensure proper configuration

This webhook allows Stripe to notify your application when payments are completed, ensuring order processing even if customers close their browser after payment.

TODO

In `src/Listener/ProductItemListener.php` we need to create an empty `ProductItemMedia|ProductItemFile` if none is added, otherwise we can't add one afterwards. The physical ProdutItemMedia is not deleted when the ProductItem is deleted, but the link is removed. See `ProductItemListener->prePersist()`. Furthermoe, need to create ProductItem without Meida/File first.

A Command has been made to remove those files, simply run (and/or add incrontab) `php bin/console shop:media:delete`.
