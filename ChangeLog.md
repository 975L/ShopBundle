# Changelog

## v1.6.3

- Modified button under AmountAchieved when crowdfunding is not started or ended (01/08/2025)
- Added the possibility to upload a video for the lottery's draw (01/08/2025)

## v1.6.2

- Added Product:Card component (26/07/2025)
- Added Product:Button component (26/07/2025)

## v1.6.1

- Added a Slider component to be reused using product.medias (19/07/2025)

## v1.6

- Increased width for resized image and quality (19/07/2025)

## v1.5.1

- Added user's message to email and basket display (29/06/2025)
- Added possibility to flag a ProductItem as a service with no shipping (29/06/2025)

## v1.5

- Added message field on basket (29/06/2025)

## v1.4

- Added missing pagination for shop (26/06/2025)
- Modified default number of products to 12 (26/06/2025)

## v1.3.1

- Modified CTA button to allow other icon (09/06/2025)

## v1.3

- Added button cta for crowdfunding (06/06/2025)

## v1.2.1

- Corrected site name in Dashboard (29/05/2025)
- Added possibility to have html in counterpart (29/05/2025)
- Added help message at top form for Counterpart (29/05/2025)

## v1.2

- Corrected possibility when basket total is 0 (27/05/2025)

## v1.1.1

- Corrected label.add_news (27/05/2025)

## v1.1

- Corrected text alignment for lottery's prizes (27/05/2025)
- Emphasized limited quantity display (27/05/2025)
- Added possibility to add a YouTube video for crowdfunding (27/05/2025)

## v1.0.1

- Corrected english translations (23/05/2025)

## v1.0

- Added possibility of limitedQuantity = 0 (23/05/2025)
- Moved to production (23/05/2025)

## v0.31

- Added form to add News (23/05/2025)
- Added come back later + date on Counterpart button if crowdfunding not started (23/05/2025)

## v0.30.7

- Corrected DateImmutable (03/05/2025)
- Corrected email send for lottery tickets (03/05/2025)
- Corrected display for prizes (03/05/2025)

## v0.30.6.1

- Corrected shop.en.xlf (02/05/2025)

## v0.30.6

- Added styles (02/05/2025)
- Changed lottery prize descriptin to text (02/05/2025)
- Corrected persistence for Crowdfunding video (02/05/2025)

## v0.30.5

- Added number of the counterpart (02/05/2025)

## v0.30.4

- Modified files when crowdfunding has not started yet (02/05/2025)

## v0.30.3

- Replaced addPanel by addFieldset as deprecated (02/05/2025)

## v0.30.2

- Corrected limitedQUantity on productItems (26/04/2025)
- Added limitedQuantity on CrowdfundingCounterparts (26/04/2025)
- Added registration of orderedQuantity on productItems (26/04/2025)

## v0.30.1

- Replaced c975LEmail by c975LSite as c975LEmailBundle is abandonned (25/04/2025)

## v0.30

- Corrected generate lottery tickets for all avalailable lotteries of crowdfunding (25/04/2025)

## v0.29

- Added random suffle on lottery's tickets before draw (24/04/2025)
- Added missing translations (24/04/2025)
- Added limitedQuantity field to ProductItem (24/04/2025)
- Corrected limitedQuantity on ProductItem (24/04/2025)
- Added default position for Product/Crowdfunding (24/04/2025)
- Added missing fields for CrudController (24/04/2025)
- Added a Timezone "setter" in session to display hours correctly (24/04/2025)
- Added email send to lottery's winner (24/04/2025)
- Corrected quantity for lottery tickets when purchasing more thant one counterpart (24/04/2025)

## v0.28

- Removed toast (22/04/2025)
- Replaced Basket View button by including it in a fixed bottom navbar (22/04/2025)
- Added a test mode warning displayed message (22/04/2025)
- Corrected CrowdfundingContribor->name to allow null (23/04/2025)
- Updated README (23/04/2025)
- Added lottery system for Crowdfunding (23/04/2025)

## v0.27

- Added requiresShipping on Counterpart (20/04/2025)
- Corrected counterpart type to crowdfunding (20/04/2025)
- Added Item type in place of Digital (20/04/2025)
- Added default text for customizable parts (20/04/2025)
- Changed basket digital to contentFlag to allow more possibilities (20/04/2025)
- Finished system of crowdfunding contribution (20/04/2025)
- Added limitedQuantity on ProductItem (21/04/2025)
- Split shipped in two for tiems and counterparts (21/04/2025)

## v0.26

- Finished backoffice management of Crowdfunding (16/04/2025)
- Set CrowdfundingVideo as a collection (16/04/2025)

## v0.25

- Updated MediaDeleteCommand (16/04/2025)
- - Finished corrections for Shop items (16/04/2025)

## v0.24

- Corrected relations in entities (15/04/2025)
- Added relation Counterpart -> Contributor (15/04/2025)
- Renamed parts of ProductItems to items as strategy has changed to use a type of items (15/04/2025)

## v0.23

- Removed tables for media/file and made use of only one (11/04/2025)
- Added EasyAdmin CRUD controllers for crowdfundings (11/04/2025)
- Made use of a ShopMediaNamer (11/04/2025)
- Removed MediaTrait and use of VichUploader to resize image (11/04/2025)

## v0.22

- Added amount to CrowdfundingContributor (10/04/2025)
- Added CrowdfundingVideo (10/04/2025)
- Renamed CrowdfundingCounterpart quantityAvailable -> limitedQuantity (10/04/2025)
- Renamed CrowdfundingCounterpart quantityTaken -> orderedQuantity (10/04/2025)
- Finished frontend design for crowdfunding (10/04/2025)

## v0.21

- Added beginDate for Crowdfunding (09/04/2025)
- Added styles for Crowdfunding (09/04/2025)

## v0.20.2

- Corrected product.media that was not anymore an integer (06/04/2025)

## v0.20.1

- Corrected displayed size of download files (06/04/2025)
- Added basket icon where quantity is set (06/04/2025)
- Made use of format_currency (06/04/2025)
- Added quantity purchased in readonly basket (06/04/2025)

## v0.20

- Added Crowdfunding structure (05/04/2025)

## v0.19.4

- Corrected errors on namespace (05/04/2025)

## v0.19.3

- Added no products information (05/04/2025)
- Removed basket button if no products (05/04/2025)
- Added copyright (05/04/2025)

## v0.19.2

- Modified Delivery component to delete date as redundant with above information (05/04/2025)

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