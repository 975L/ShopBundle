<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Mcp;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\UniqueSlug;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// What every shop tool checks before answering or writing: the account manages the catalog, as on the back office, a row is refused as its form would refuse it, and a slug typed is made one as the slug field makes it
class ShopMcpAccess
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly ValidatorInterface $validator,
    ) {
    }

    // The catalog is the admin's business, as on the back office
    public function guard(): void
    {
        if (!$this->security->isGranted((string) $this->configService->get('site-role-admin'))) {
            throw new ToolCallException('This account may not work on the shop\'s catalog.');
        }
    }

    // The row's constraints, as the back office's form runs them, a refused edit taken back off the managed row
    public function validate(object $entity, bool $managed = false): void
    {
        $violations = $this->validator->validate($entity);
        if (0 === count($violations)) {
            return;
        }
        if ($managed) {
            $this->entityManager->refresh($entity);
        }
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        throw new ToolCallException(implode(' ', $messages));
    }

    // A slug as an agent typed it, made a slug as the back office's slug field makes it, one made of signs only refused
    public function normalized(string $slug): string
    {
        $normalized = strtolower($this->slugger->slug(trim($slug))->toString());
        if ('' === $normalized) {
            throw new ToolCallException('The slug is empty.');
        }

        return $normalized;
    }

    // A slug from a title, the fallback for a title made of signs only, a number added while another row holds it
    /** @param callable(string): bool $taken */
    public function unique(string $title, string $fallback, callable $taken): string
    {
        $base = mb_substr($title, 0, 90);

        return UniqueSlug::build($this->slugger, '' === $this->slugger->slug($base)->toString() ? $fallback : $base, $taken);
    }
}
