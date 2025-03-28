# ShopBundle
Shop Bundle for eCommerce with Symfony

**BUNDLE UNDER DEVELOPMENT, USE AT YOUR OWN RISKS**

Define a User Class in `config\config_bundles.yaml`.

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

TODO

In `src/Listener/ProductItemListener.php` we need to create an empty `ProductItemMedia|ProductItemFile` if none is added, otherwise we can't add one afterwards. The physical ProdutItemMedia is not deleted when the ProductItem is deleted, but the link is removed. See `ProductItemListener->prePersist()`. Furthermoe, need to create ProductItem without Meida/File first.

A Command has been made to remove thse files, simply run (and/or add incrontab) `php bin/console shop:media:delete`.

The bundle relies on the use of `App\Entity\User`.