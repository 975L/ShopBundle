<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Form\Block;

use c975L\ShopBundle\Form\Block\CategoriesBlockType;
use c975L\ShopBundle\Form\Block\GiftCardsBlockType;
use c975L\ShopBundle\Form\Block\ProductBlockType;
use c975L\ShopBundle\Form\Block\ProductButtonBlockType;
use c975L\ShopBundle\Form\Block\ProductItemsBlockType;
use c975L\ShopBundle\Form\Block\ProductsBlockType;
use c975L\ShopBundle\Form\Block\ProductSearchBlockType;
use c975L\ShopBundle\Form\Block\ProductSliderBlockType;
use c975L\ShopBundle\Form\Block\RecommendationsBlockType;
use c975L\ShopBundle\Service\ShopBlockChoices;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// The nine kinds this bundle registers, checked on what a block editor actually depends on: which field is required, and in which domain the labels are looked up
class BlockTypesTest extends TestCase
{
    /** @return array<string, array{type: ?string, options: array<string, mixed>}> */
    private function build(AbstractType $type): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $formType = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $formType, 'options' => $options];

            return $builder;
        });

        $type->buildForm($builder, []);

        return $added;
    }

    private function choices(): ShopBlockChoices
    {
        $choices = $this->createStub(ShopBlockChoices::class);
        $choices->method('products')->willReturn(['Affiche' => 'affiche']);
        $choices->method('categories')->willReturn(['Affiches' => 'affiches']);

        return $choices;
    }

    /** @return list<array{0: class-string<AbstractType>}> */
    public static function typeProvider(): array
    {
        return [
            [ProductsBlockType::class],
            [CategoriesBlockType::class],
            [ProductBlockType::class],
            [ProductButtonBlockType::class],
            [ProductSearchBlockType::class],
            [RecommendationsBlockType::class],
            [ProductItemsBlockType::class],
            [ProductSliderBlockType::class],
            [GiftCardsBlockType::class],
        ];
    }

    // BlockType translates the embedded data form in the "ui" domain: a kind forgetting to say otherwise renders every one of its labels raw
    #[\PHPUnit\Framework\Attributes\DataProvider('typeProvider')]
    public function testEveryKindLooksItsLabelsUpInTheShopDomain(string $class): void
    {
        $type = \in_array($class, [CategoriesBlockType::class, GiftCardsBlockType::class], true) ? new $class() : new $class($this->choices());

        $resolver = new OptionsResolver();
        $resolver->setDefaults(['translation_domain' => 'ui']);
        $type->configureOptions($resolver);

        $this->assertSame('shop', $resolver->resolve()['translation_domain']);
    }

    public function testTheProductsListingOffersTheWholeShopByDefault(): void
    {
        $added = $this->build(new ProductsBlockType($this->choices()));

        $this->assertSame(ChoiceType::class, $added['categorySlug']['type']);
        $this->assertFalse($added['categorySlug']['options']['required']);
        $this->assertSame('label.block_all_categories', $added['categorySlug']['options']['placeholder']);
        $this->assertSame(['Affiches' => 'affiches'], $added['categorySlug']['options']['choices']);
        $this->assertSame(IntegerType::class, $added['max']['type']);
        $this->assertSame(CheckboxType::class, $added['random']['type']);
    }

    // A shop has a handful of visuals and not a catalogue of them: nothing to point at, nothing to draw at random, just how many to show
    public function testTheGiftCardsListingOffersOnlyATitleAndACount(): void
    {
        $added = $this->build(new GiftCardsBlockType());

        $this->assertSame(['title', 'max'], array_keys($added));
        $this->assertSame(IntegerType::class, $added['max']['type']);
        $this->assertFalse($added['max']['options']['required']);
    }

    // A card, and the button beside it, have nothing to show without a product - where the three sheet kinds read theirs from the sheet they sit on
    public function testOnlyTheCardAndTheButtonRequireAProduct(): void
    {
        foreach ([new ProductBlockType($this->choices()), new ProductButtonBlockType($this->choices())] as $type) {
            $added = $this->build($type);
            $this->assertTrue($added['productSlug']['options']['required']);
            $this->assertInstanceOf(NotBlank::class, $added['productSlug']['options']['constraints'][0]);
            $this->assertSame('label.block_product_placeholder', $added['productSlug']['options']['placeholder']);
        }

        foreach ([new RecommendationsBlockType($this->choices()), new ProductItemsBlockType($this->choices()), new ProductSliderBlockType($this->choices())] as $type) {
            $added = $this->build($type);
            $this->assertFalse($added['productSlug']['options']['required']);
            $this->assertSame('label.block_current_product', $added['productSlug']['options']['placeholder']);
        }
    }

    // The five button styles are UiBundle's own, so the two kinds paint the same buttons
    public function testTheProductButtonOffersTheSameStylesAsUiBundle(): void
    {
        $added = $this->build(new ProductButtonBlockType($this->choices()));

        $this->assertSame(['label.primary' => 'primary', 'label.secondary' => 'secondary', 'label.success' => 'success', 'label.danger' => 'danger', 'label.link' => 'link'], $added['type']['options']['choices']);
    }

    public function testTheSearchIsRestrictedToACategoryOrToNoneAtAll(): void
    {
        $added = $this->build(new ProductSearchBlockType($this->choices()));

        // The heading is optional, the shop's own index stating none above its filter row
        $this->assertFalse($added['title']['options']['required']);
        $this->assertFalse($added['categorySlug']['options']['required']);
        $this->assertSame('label.block_all_categories', $added['categorySlug']['options']['placeholder']);
    }
}
