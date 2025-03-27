# ShopBundle
Shop Bundle for eCommerce with Symfony


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
```

TODO

In `src/Listener/ProductItemListener.php` we need to create an empty `ProductItemMedia` if none is added, otherwise we can't add one afterwards. The physical ProdutItemMedia is not deleted when the ProductItem is deleted, but the link is removed.