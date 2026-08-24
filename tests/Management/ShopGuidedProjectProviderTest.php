<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ShopBundle\Management\ShopGuidedProjectProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ShopGuidedProjectProviderTest extends TestCase
{
    private function createAdminUrlGenerator(array &$controllers = []): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $controller) use ($generator, &$controllers) {
            $controllers[] = $controller;

            return $generator;
        });
        $generator->method('setAction')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/shop');

        return $generator;
    }

    private function createUrlGenerator(array &$routes = []): UrlGeneratorInterface
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static function (string $route) use (&$routes): string {
                $routes[] = $route;

                return '/management/' . $route;
            }
        );

        return $generator;
    }

    private function createProvider(array &$controllers = [], array &$routes = []): ShopGuidedProjectProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return new ShopGuidedProjectProvider(
            $this->createAdminUrlGenerator($controllers),
            $configService,
            $this->createUrlGenerator($routes),
        );
    }

    // The 8000 block GuidedProjectProviderInterface reserves this bundle, at the step of 10 it states
    public function testGetGuidedProjectsRunsTheBundleOwnBlock(): void
    {
        $projects = $this->createProvider()->getGuidedProjects();

        $this->assertSame(
            ['shop-category', 'shop-product', 'shop-downloadable', 'shop-gift-card', 'shop-test-mode', 'shop-export'],
            array_column($projects, 'slug'),
        );
        $this->assertSame([8010, 8020, 8030, 8040, 8050, 8060], array_column($projects, 'order'));
    }

    public function testEverySlugIsPrefixedWithTheBundleName(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertStringStartsWith('shop-', $project['slug'], 'A slug is unique across every bundle contributing projects');
        }
    }

    public function testEveryProjectCarriesTheShopTranslationDomainAndSteps(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame('shop', $project['translation_domain']);
            $this->assertNotEmpty($project['steps']);
        }
    }

    // Both catalog screens gate their own index by the site's admin role, so a parcours walking them is dropped for anybody else
    public function testEveryProjectCarriesTheAdminRole(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame('ROLE_ADMIN', $project['role']);
        }
    }

    public function testNoStepSetsBothUrlAndHighlight(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                $this->assertFalse(
                    isset($step['url']) && isset($step['highlight']),
                    sprintf('Step %d of "%s" sets both url and highlight', $index, $project['slug'])
                );
            }
        }
    }

    // Only the opening step leaves the screen, everything after it walking the one the user has been sent to - which is why creating a category and creating a product are two projects rather than one
    public function testOnlyTheFirstStepOfEachProjectCarriesAnUrl(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $steps = $project['steps'];

            $this->assertArrayHasKey('url', $steps[0], sprintf('Project "%s" does not open on a screen', $project['slug']));

            foreach (array_slice($steps, 1) as $index => $step) {
                $this->assertArrayNotHasKey('url', $step, sprintf('Step %d of "%s" leaves the screen again', $index + 1, $project['slug']));
            }
        }
    }

    // Each parcours opens on the listing its task starts from, the four written from the products one included
    public function testEachCrudProjectOpensOnItsOwnListing(): void
    {
        $controllers = [];
        $this->createProvider($controllers)->getGuidedProjects();

        $this->assertSame(
            ['ProductCategoryCrudController', 'ProductCrudController', 'ProductCrudController', 'ProductCrudController', 'ProductCrudController'],
            array_map(static fn (string $fqcn): string => basename(str_replace('\\', '/', $fqcn)), $controllers),
        );
    }

    // The test-mode toggle is a dashboard shortcut, not a screen of this bundle
    public function testTheTestModeProjectOpensOnTheDashboard(): void
    {
        $controllers = [];
        $routes = [];
        $this->createProvider($controllers, $routes)->getGuidedProjects();

        $this->assertSame(['management'], $routes);
    }

    // Both toggle steps highlight the button ShopShortcutController's own route renders on the dashboard
    public function testTheTestModeToggleStepsHighlightTheShortcutButton(): void
    {
        $project = $this->createProvider()->getGuidedProjects()[4];
        $highlights = array_values(array_filter(array_column($project['steps'], 'highlight')));

        $this->assertSame(
            ['form[action$="/shop/test-mode-toggle"] button', 'form[action$="/shop/test-mode-toggle"] button'],
            $highlights,
        );
    }

    // EasyAdmin renders a button as `action-<actionName>`, so a highlight guessing at the name points at nothing
    public function testEveryHighlightedActionIsAnEasyAdminOne(): void
    {
        $actions = $this->easyAdminActionNames();

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                if (!isset($step['highlight']) || !preg_match('/^\.action-(\w+)$/', $step['highlight'], $matches)) {
                    continue;
                }

                $this->assertContains(
                    $matches[1],
                    $actions,
                    sprintf('Step %d of "%s" highlights an action EasyAdmin does not render', $index, $project['slug'])
                );
            }
        }
    }

    private function easyAdminActionNames(): array
    {
        $constants = new \ReflectionClass(Action::class)->getConstants();

        return [...array_values(array_filter(
            $constants,
            static fn (string $name): bool => !str_starts_with($name, 'TYPE_'),
            ARRAY_FILTER_USE_KEY
        )), ...$this->customActionNames()];
    }

    // The names this bundle's CRUD controllers declare themselves, read off their source: EasyAdmin renders `action-<name>` for them just the same, and a highlight pointing at one would fail the check above otherwise
    private function customActionNames(): array
    {
        $names = [];
        foreach (glob(\dirname(__DIR__, 2) . '/src/Controller/Management/*CrudController.php') ?: [] as $file) {
            preg_match_all("/Action::new\\('(\\w+)'/", (string) file_get_contents($file), $matches);
            $names = [...$names, ...$matches[1]];
        }

        return array_values(array_unique($names));
    }

    // A form field carries `<Entity>_<property>` as its id, so a highlight naming a property the screen stopped declaring points at nothing
    public function testEveryHighlightedFieldIsStillDeclaredByItsScreen(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                if (!isset($step['highlight']) || !preg_match('/(?:#|input=")(Product|ProductCategory)_(\w+)/', $step['highlight'], $matches)) {
                    continue;
                }

                $this->assertContains(
                    $matches[2],
                    $this->fieldNames($matches[1]),
                    sprintf('Step %d of "%s" highlights a property %s no longer declares', $index, $project['slug'], $matches[1])
                );
            }
        }
    }

    // The properties the CRUD controller hands to configureFields(), read off its source rather than booted through EasyAdmin
    private function fieldNames(string $entity): array
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Controller/Management/' . $entity . 'CrudController.php');
        preg_match_all("/Field::new\\('(\\w+)'/", $source, $matches);

        return $matches[1];
    }

    // The collection holding the prices is numbered by EasyAdmin, so the projects point at the attribute ProductCrudController sets on it instead of at an id that moves with the entries
    public function testTheItemsCollectionIsHighlightedByItsOwnAttribute(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Controller/Management/ProductCrudController.php');

        $this->assertStringContainsString("'data-shop-product-items' => '1'", $source);
    }

    // A label or description with no translation reads as its own key in the panel, in whichever locale it is missing from
    public function testEveryLabelAndDescriptionIsTranslatedInEveryLocale(): void
    {
        foreach (['en', 'fr', 'es'] as $locale) {
            $translated = $this->translatedKeys($locale);

            foreach ($this->createProvider()->getGuidedProjects() as $project) {
                foreach ([$project, ...$project['steps']] as $item) {
                    $this->assertContains($item['label'], $translated, sprintf('"%s" is missing from the %s catalogue', $item['label'], $locale));
                    if (isset($item['description'])) {
                        $this->assertContains($item['description'], $translated, sprintf('"%s" is missing from the %s catalogue', $item['description'], $locale));
                    }
                }
            }
        }
    }

    private function translatedKeys(string $locale): array
    {
        $xliff = new \DOMDocument();
        $xliff->load(\dirname(__DIR__, 2) . '/translations/shop.' . $locale . '.xlf');

        $keys = [];
        foreach ($xliff->getElementsByTagName('source') as $source) {
            $keys[] = $source->textContent;
        }

        return $keys;
    }
}
