<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Entity;

use c975L\ShopBundle\Entity\Product;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// The age is typed in a free text field of the back-office, against a column of 20 characters and a format the graph reads off it - anything else used to be saved, or to answer a 500
class ProductValidationTest extends TestCase
{
    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    // The property alone: validating the whole entity would pull in its UniqueEntity, whose validator only exists once Doctrine is booted
    private function violations(?string $age): int
    {
        return $this->validator()->validateProperty(new Product()->setAge($age), 'age')->count();
    }

    // The two shapes ProductSnippetBuilder::audience() reads, and the field being optional
    public function testTheRangesTheGraphReadsAreAccepted(): void
    {
        $this->assertSame(0, $this->violations('3-8'));
        $this->assertSame(0, $this->violations('15-'));
        $this->assertSame(0, $this->violations('3'));
        $this->assertSame(0, $this->violations(null));
    }

    // A sentence was accepted then silently dropped by the graph, and one longer than the column answered a 500 instead of a form error
    public function testASentenceIsRefused(): void
    {
        $this->assertGreaterThan(0, $this->violations('de 3 a 8 ans environ'));
        $this->assertGreaterThan(0, $this->violations('from three to eight years old'));
    }

    // The second figure exists only with its dash: written without one, "3 8" and "123456" used to pass and to be published as a range nobody typed
    public function testTwoFiguresWithoutADashAreRefused(): void
    {
        $this->assertGreaterThan(0, $this->violations('3 8'));
        $this->assertGreaterThan(0, $this->violations('123456'));
        $this->assertSame(0, $this->violations('3-8'));
        $this->assertSame(0, $this->violations('8 - 12'));
    }

    // The one shape the pattern cannot tell apart from a range, and the one the graph dropped in silence
    public function testARangeWrittenBackwardsIsRefused(): void
    {
        $this->assertSame(1, $this->rangeViolations('8-3'));
        $this->assertSame(0, $this->rangeViolations('3-8'));
        $this->assertSame(0, $this->rangeViolations('8-8'));
        $this->assertSame(0, $this->rangeViolations('15-'));
        $this->assertSame(0, $this->rangeViolations(null));
    }

    // The callback is written on the class, where validateProperty() does not reach it
    private function rangeViolations(?string $age): int
    {
        return $this->validator()->validate(new Product()->setAge($age), new Assert\Callback('validateAgeRange'))->count();
    }
}
