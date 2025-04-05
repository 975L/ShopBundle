# Changelog

## v0.19.1

- Updated Readme (05/04/2025)
- Added Track order steps even if not done to give information to user (05/04/2025)

# v0.19

- Corrected DownloadLinks (04/04/2025)
- Added desactivation of + button in basket when digital product as it has no sense (04/04/2025)
- Added join on queries to optimize them (04/04/2025)
- Enhanced basket AddRemoveButtons (04/04/2025)
- Added slug for productItem (04/04/2025)

# v0.18

- Added checkboxes for Terms of use/Terms of sales (03/04/2025)
- Moved back sending email to BasketService->paid() to avoid webhook not reached (03/04/2025)
- Added file size for download items, to indicate in emails (03/04/2025)
- Added ProductItem slug to downloaded filename to make it more clear (03/04/2025)
- Added file size to download email (03/04/2025)
- Added action to send email when physical products are sent (03/04/2025)
- Removed Basket paymentIdentifier (03/04/2025)
- Added Basket shipped/downloaded datetime (03/04/2025)
- Added securityToken for Basket, to be used in url, to avoid basket visibility with only its number (03/04/2025)

## v0.17.1

- Removed \ in Twig component as deprecated (02/04/2025)
- Added Email if Stripe error (02/04/2025)

## v0.17

- Corrected missing root link for sitemap (02/04/2025)
- Corrected Update of Product (02/04/2025)
- Removed use of StripeVersion (02/04/2025)
- Moved ApiKey to bundle.yaml (02/04/2025)
- Added Webhook support (02/04/2025)
- Removed unsued Payment fields (02/04/2025)
- Added Payment->stripeMethod (02/04/2025)
- Added link to Stripe payment from dashboard (02/04/2025)

## v0.16.1

- Added customer_email sent to StripeCheckout (02/04/2025)
- Made use of Messenger for confirmation order email (02/04/2025)
- Corrected basket not updated after payment and emails not sent (02/04/2025)
- Added forced download for productItemDownload (02/04/2025)

# v0.16

- Added intermediate step before basket validation (01/04/2025)
- Removed empty/validated basket templates and added functionnality in display one (01/04/2025)
- Added crontab to delete unvalidated baskets after 14 days (01/04/2025)
- Corrected user to null in Product (and sub entites) when adding to basket (01/04/2025)
- Added Command for creating sitemap (01/04/2025)

# v0.15.1

- Corrected display of product item file icon (29/03/2025)

# v0.15

- Corrected social for product (29/03/2025)
- Corrected differents texts + presentation (29/03/2025)
- Added esponse for item already downloaded (29/03/2025)
- Removed discouraged uses  (29/03/2025)
- Changed isNumeric to isDigital and transformed it to have 3 possible states (29/03/2025)
- Added Command to update position por Products, Items and Medias (29/03/2025)
- Added icon on ProductItemMedia (29/03/2025)

# v0.14.1

- Corrected template name (28/03/2025)

# v0.14

- Corrected email for download (28/03/2025)
- Added Command to remove download files (28/03/2025)

# v0.13

- Transformed productItem->file as a VichUploadable File (28/03/2025)
- Added file download after purchase (28/03/2025)

# v0.12

- Added image for ProductItem (27/03/2025)
- Added position for Product (27/03/2025)
- Added position for ProductItem (27/03/2025)
- Added position for ProductMedia (27/03/2025)
- Added display of id in shop management (27/03/2025)
- Added name field for basket (27/03/2025)
- Corrected display images on basket + email (27/03/2025)
- Added possibility to delete Basket/Payment for Admin (27/03/2025)
- Added links Payment <-> Basket (27/03/2025)
- Added buttons to filter Baskets (27/03/2025)
- Renamed ImageTrait to MediaTrait, more consistent (27/03/2025)

## v0.11

- Changed basket number format  (24/03/2025)
- Added Codacy corrections (24/03/2025)
- Added /shop to Basket Routes (24/03/2025)
- Added componenets to be overrided to allow perosnalisation (24/03/2025)

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