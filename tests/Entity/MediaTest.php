<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Entity;

use c975L\ShopBundle\Entity\ProductMedia;
use PHPUnit\Framework\TestCase;

// What this bundle's own media hierarchy says of a picture beyond the file itself, which is what UiBundle's Image and Slider components read of it
class MediaTest extends TestCase
{
    // The one field of a product picture a search engine and a screen reader both read, and the only one stored: the trait it shares with the other bundles says what the file is, never what it shows
    public function testAPictureCarriesItsOwnAlternativeText(): void
    {
        $media = new ProductMedia()->setAlt('Affiche A2 encadrée');

        $this->assertSame('Affiche A2 encadrée', $media->getAlt());
    }

    public function testAPictureLeftWithoutAnAlternativeTextCarriesNone(): void
    {
        $this->assertNull(new ProductMedia()->getAlt());
    }

    // Answered from the file name rather than stored: an upload already names its own format, and a column would be a second place for it to be wrong
    public function testTheMimeTypeIsReadFromTheFileName(): void
    {
        $this->assertSame('image/webp', new ProductMedia()->setName('affiche.webp')->getMimeType());
        $this->assertSame('image/jpeg', new ProductMedia()->setName('affiche.jpeg')->getMimeType());
    }

    // The slider tells a video from a picture on this very answer, and plays the ones it recognises
    public function testAVideoIsRecognisedAsOne(): void
    {
        $this->assertSame('video/mp4', new ProductMedia()->setName('demo.MP4')->getMimeType());
        $this->assertSame('video/webm', new ProductMedia()->setName('demo.webm')->getMimeType());
        // Named rather than prefixed: "video/ogv" is no mime type, and a source typed with one the browser does not know is skipped
        $this->assertSame('video/ogg', new ProductMedia()->setName('demo.ogv')->getMimeType());
    }
}
