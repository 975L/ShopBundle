<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Form;

use c975L\ShopBundle\Form\ProductItemMediaType;
use c975L\ShopBundle\Form\ProductMediaType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;

// A product's and an item's pictures are pictures: no script, no page and no svg is served from the site's own origin
class MediaUploadTypesTest extends TestCase
{
    /** @return iterable<string, array{AbstractType}> */
    public static function pictureTypes(): iterable
    {
        yield 'product' => [new ProductMediaType()];
        yield 'product item' => [new ProductItemMediaType()];
    }

    #[DataProvider('pictureTypes')]
    public function testThePicturesOnlyAcceptImages(AbstractType $type): void
    {
        $fields = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $class = null, array $options = []) use (&$fields, $builder) {
            $fields[$name] = $options;

            return $builder;
        });

        $type->buildForm($builder, []);

        $constraint = $fields['file']['constraints'][0];
        $this->assertInstanceOf(File::class, $constraint);
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp', 'image/gif'], $constraint->mimeTypes);
    }
}
