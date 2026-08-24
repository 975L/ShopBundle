<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Service;

use c975L\ShopBundle\Entity\Product;
use c975L\ShopBundle\Entity\ProductItem;
use c975L\ShopBundle\Entity\ProductItemFile;
use c975L\ShopBundle\Repository\ProductCategoryRepository;
use c975L\ShopBundle\Repository\ProductRepository;
use c975L\ShopBundle\Service\ProductStateService;
use c975L\ShopBundle\Service\ShopService;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;

// The order the listing is asked for, and the one thing the repository cannot do itself: ordering on the lowest price of a product's items
class ShopServiceTest extends TestCase
{
    /** @var Product[] the array the paginator was handed, captured by the stub below */
    private array $paginated = [];

    public function testAnOrderTheListingDoesNotOfferFallsBackOnTheShopsOwnPositions(): void
    {
        $service = $this->service();

        $this->assertNull($service->getOrder(new InputBag()));
        $this->assertNull($service->getOrder(new InputBag(['order' => 'title'])));
        $this->assertNull($service->getOrder(new InputBag(['order' => ''])));
    }

    public function testTheThreeOrdersOfTheListingAreAccepted(): void
    {
        $service = $this->service();

        $this->assertSame('newest', $service->getOrder(new InputBag(['order' => 'newest'])));
        $this->assertSame('price_asc', $service->getOrder(new InputBag(['order' => 'price_asc'])));
        $this->assertSame('price_desc', $service->getOrder(new InputBag(['order' => 'price_desc'])));
    }

    public function testTheListingIsOrderedOnTheLowestPriceOfTheItems(): void
    {
        $this->service($this->products())->findAllProductsPaginated(new InputBag(['order' => 'price_asc']));

        $this->assertSame(['cheap', 'medium', 'dear'], $this->titles());
    }

    public function testTheOppositeOrderReversesIt(): void
    {
        $this->service($this->products())->findAllProductsPaginated(new InputBag(['order' => 'price_desc']));

        $this->assertSame(['dear', 'medium', 'cheap'], $this->titles());
    }

    // A product carrying no item has no price to be ordered on, and closes the list rather than leading it at zero
    public function testAProductWithNoItemClosesTheListWhicheverWay(): void
    {
        $products = array_merge($this->products(), [$this->product('empty')]);

        $this->service($products)->findAllProductsPaginated(new InputBag(['order' => 'price_asc']));
        $this->assertSame('empty', $this->titles()[3]);

        $this->service($products)->findAllProductsPaginated(new InputBag(['order' => 'price_desc']));
        $this->assertSame('empty', $this->titles()[3]);
    }

    // Without an order, the listing is handed over exactly as the repository sorted it
    public function testTheRepositoryOrderIsLeftAloneWhenNothingIsAsked(): void
    {
        $this->service($this->products())->findAllProductsPaginated(new InputBag());

        $this->assertSame(['dear', 'cheap', 'medium'], $this->titles());
    }

    public function testTheCategoriesAreCounted(): void
    {
        $categoryRepository = $this->createStub(ProductCategoryRepository::class);
        $categoryRepository->method('findAll')->willReturn(['a', 'b', 'c']);

        $this->assertSame(3, $this->service([], $categoryRepository)->countCategories());
    }

    public function testAFilterTheListingDoesNotOfferIsIgnored(): void
    {
        $filters = $this->service()->getFilters(new InputBag(['price' => 'cheap', 'format' => 'poster', 'stock' => 'yes']));

        $this->assertSame(['price' => null, 'format' => null, 'stock' => null], $filters);
    }

    public function testTheThreeFiltersAreReadFromTheQuery(): void
    {
        $filters = $this->service()->getFilters(new InputBag(['price' => '1000-2500', 'format' => 'digital', 'stock' => 'available']));

        $this->assertSame(['price' => '1000-2500', 'format' => 'digital', 'stock' => 'available'], $filters);
    }

    // A band whose upper bound is open is what the last one carries, and what a url written by hand may carry too
    public function testAnOpenEndedPriceRangeIsAccepted(): void
    {
        $this->assertSame('3000-', $this->service()->getFilters(new InputBag(['price' => '3000-']))['price']);
    }

    public function testThePriceBandsAreCutFromTheDearestItem(): void
    {
        // 92 € rounded up to 23 € a band, the last one left open-ended
        $brackets = $this->service([], null, 9200)->getPriceBrackets();

        $this->assertCount(4, $brackets);
        $this->assertSame(['value' => '0-2300', 'min' => 0, 'max' => 2300], $brackets[0]);
        $this->assertSame(['value' => '6900-', 'min' => 6900, 'max' => null], $brackets[3]);
    }

    public function testAShopWithNothingPricedOffersNoPriceBands(): void
    {
        $this->assertSame([], $this->service()->getPriceBrackets());
    }

    public function testThePriceFilterNarrowsTheListingOnTheLowestPrice(): void
    {
        $this->service($this->products())->findAllProductsPaginated(new InputBag(['price' => '1000-4000']));

        $this->assertSame(['medium'], $this->titles());
    }

    // The upper bound is exclusive, so two neighbouring bands never both hold the same product
    public function testAPriceBandExcludesItsUpperBound(): void
    {
        $this->service($this->products())->findAllProductsPaginated(new InputBag(['price' => '900-1900']));

        $this->assertSame(['cheap'], $this->titles());
    }

    public function testTheFormatFilterNarrowsOnWhatTheItemsAreSoldAs(): void
    {
        $products = [$this->product('poster', 1900), $this->downloadable('ebook'), $this->serviceProduct('coaching')];

        $this->service($products)->findAllProductsPaginated(new InputBag(['format' => 'digital']));
        $this->assertSame(['ebook'], $this->titles());

        $this->service($products)->findAllProductsPaginated(new InputBag(['format' => 'service']));
        $this->assertSame(['coaching'], $this->titles());
    }

    // "In stock" is what the card itself calls in stock, so the two can never disagree
    public function testTheStockFilterLeavesOutWhatCannotBeBought(): void
    {
        $available = $this->product('available', 1900);
        $available->getItems()[0]->setLimitedQuantity(null);

        $soldOut = $this->product('sold-out', 1900);
        $soldOut->getItems()[0]->setLimitedQuantity(2)->setOrderedQuantity(2);

        $this->service([$available, $soldOut])->findAllProductsPaginated(new InputBag(['stock' => 'available']));

        $this->assertSame(['available'], $this->titles());
    }

    public function testAListingAskedForNoFilterIsHandedOverWhole(): void
    {
        $this->service($this->products())->findAllProductsPaginated(new InputBag());

        $this->assertCount(3, $this->paginated);
    }

    // The filters and the order apply together rather than one cancelling the other
    public function testAFilteredListingIsStillOrdered(): void
    {
        $this->service($this->products())->findAllProductsPaginated(new InputBag(['price' => '0-5000', 'order' => 'price_asc']));

        $this->assertSame(['cheap', 'medium', 'dear'], $this->titles());
    }

    // A service whose repository returns the given products and whose paginator only records what it was handed
    private function service(array $products = [], ?ProductCategoryRepository $categoryRepository = null, int $maxItemPrice = 0): ShopService
    {
        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('findAllSorted')->willReturn($products);
        $productRepository->method('findMaxLowestItemPrice')->willReturn($maxItemPrice);

        $paginator = $this->createStub(PaginatorInterface::class);
        $paginator->method('paginate')->willReturnCallback(function (mixed $target): PaginationInterface {
            $this->paginated = $target;

            return $this->createStub(PaginationInterface::class);
        });

        return new ShopService(
            $productRepository,
            $categoryRepository ?? $this->createStub(ProductCategoryRepository::class),
            new ProductStateService(),
            $paginator,
        );
    }

    /**
     * @return string[]
     */
    private function titles(): array
    {
        return array_map(static fn (Product $product): string => (string) $product->getTitle(), $this->paginated);
    }

    // Deliberately not in price order, so an assertion on the result cannot pass by accident
    private function products(): array
    {
        return [
            $this->product('dear', 4900),
            $this->product('cheap', 900),
            $this->product('medium', 1900),
        ];
    }

    // A product sold as a downloaded file, which is what the "digital" format reads
    private function downloadable(string $title): Product
    {
        $product = $this->product($title, 1900);
        $product->getItems()[0]->setFile(new ProductItemFile()->setName('livre.pdf'));

        return $product;
    }

    // A product sold as a rendered service
    private function serviceProduct(string $title): Product
    {
        $product = $this->product($title, 1900);
        $product->getItems()[0]->setService(true);

        return $product;
    }

    private function product(string $title, ?int $price = null): Product
    {
        $product = new Product()->setTitle($title);

        if (null !== $price) {
            $product->addItem(new ProductItem()->setPrice($price));
        }

        return $product;
    }
}
