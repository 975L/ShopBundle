# Changelog

## v0.10

- Renamed table shop_media to shop_product_media (23/03/2025)
- Added ProductItem to manage the different versions of a product (23/03/2025)
- Renamed basket->products to productItems (23/03/2025)
- Added search bar component (23/03/2025)

## v0.9.1

- Corrections due to Codacy analysis (22/03/2025)

## v0.9

- Added relation to user in entities (22/03/2025)
- Suppressed payment->number as redundant (22/03/2025)
- Suppressed basket->identifier as not used because of number (22/03/2025)
- Changed format of basket number to be abale to have it in url but not predictable (22/03/2025)
- Move product added message below Basket (22/03/2025)
- Added +/- buttons (22/03/2025)

## v0.8.3

- Added a check to see if entity has already been processed for resizeImage() (09/03/2025)

## v0.8.2

- Added raw for product description (09/03/2025)

## v0.8.1

- Corrected call of getProductMediasNames() (09/03/2025)

## v0.8

- Added require of vich uploader to composer.json (03/03/2025)
- Renamed Media to PrductMedia (03/03/2025)
- Added ROLE_ADMIN requirement for shop management (03/03/2025)
- Added link to shop from dashboard (09/03/2025)
- Corrected component to display no product image in case of no image (09/03/2025)
- Added resize of photo for ProductMedia (09/03/2025)
- Added default image for ProductMedia (09/03/2025)

## v0.7

- Added "shop/" to product url  (01/03/2025)
- Corrected translation for country (01/03/2025)
- Changed Product field isNumeric to file (01/03/2025)
- Renamed table stripe_payment to shop_stripe_payment (01/03/2025)
- Suppressed description from Payment as not useful (01/03/2025)
- Renamed Payment->orderId to number as in basket (01/03/2025)
- Added management of shop via EasyAdmin (made use of word management instaed of admin as less used) (01/03/2025)

## v0.6.2

- Added link to product from its title (22/02/2025)

## v0.6.1

- Added shipping by default for basket creation (22/02/2025)
- Added currency in config (22/02/2025)

## v0.6

- Added basket number (13/12/2024)
- Added email sent to customer (13/12/2024)
- Added shipping (22/02/2025)
- Modified styles for products and basket (22/02/2025)

## v0.5.1

- Added translation domain by default (07/11/2024)
- Change "readonly" for product and based on basket.status, more accurate (07/11/2024)

## v0.5

- Finalized payment process (07/11/2024)
- Added empty and validated baskets (07/11/2024)

## v0.4

- Added FormType (06/11/2024)
- Added address fields as independant fields (06/11/2024)
- Added removal of a product (06/11/2024)
- Added translations (06/11/2024)
- Renamed Cart to Basket (06/11/2024)

## v0.3.1

- Corrected Cart entity (05/11/2024)

## v0.3

- Added Cart actions (05/11/2024)

## v0.2

- Revival of Bundle (26/09/2024)
- Added main Product (26/09/2024)
- Added Cart (26/09/2024)
- Added Media (26/09/2024)

## v0.1.2

- Corrected `composer.json` (05/12/2018)

## v0.1.1

- Removed required in composer.json (22/05/2018)
- Changed required versions in composer.json (04/12/2018)

## v0.1

- Creation of bundle (14/09/2017)