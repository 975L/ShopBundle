# ShopBundle
Shop Bundle for eCommerce with Symfony


Define a User Class in `config\config_bundles.yaml`.

In `config/packages/security.yaml` add the following configuration:

```yaml
    access_control:
        - { path: ^/shop/management, roles: ROLE_ADMIN }
```