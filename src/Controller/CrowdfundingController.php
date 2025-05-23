<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\Crowdfunding;
use c975L\ShopBundle\Entity\CrowdfundingNews;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use c975L\ShopBundle\Form\CrowdfundingNewsType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use c975L\ShopBundle\Service\CrowdfundingServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CrowdfundingController extends AbstractController
{
    public function __construct(private readonly CrowdfundingServiceInterface $crowdfundingService)
    {
    }

    // INDEX
    #[Route(
        '/crowdfunding',
        name: 'crowdfunding_index',
        methods: ['GET']
    )]
    public function index(): Response
    {
        return $this->render(
            '@c975LShop/crowdfunding/index.html.twig',
            [
                'crowdfundings' => $this->crowdfundingService->findAllSorted(),
            ]
        )->setMaxAge(3600);
    }

    // DISPLAY
    #[Route(
        '/crowdfunding/{slug}',
        name: 'crowdfunding_display',
        requirements: ['slug' => '^([a-zA-Z0-9\-]*)'],
        methods: ['GET', 'POST']
    )]
    public function display(
        Request $request,
        #[MapEntity(expr: 'repository.findOneBySlug(slug)')]
        Crowdfunding $crowdfunding
    ): Response
    {
        //Defines form
        $form = null;
        if (null !== $this->getUser() && ($this->getUser()->getId() === $crowdfunding->getUser()->getId() || $this->isGranted('ROLE_ADMIN'))) {
            $news = new CrowdfundingNews();
            $form = $this->crowdfundingService->createForm('news', $news);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $this->crowdfundingService->addNews($crowdfunding, $news);

                return $this->redirectToRoute('crowdfunding_display', [
                    'slug' => $crowdfunding->getSlug(),
                    '_fragment' => 'news',
                ]);
            }
        }

        return $this->render(
            '@c975LShop/crowdfunding/display.html.twig',
            [
                'crowdfunding' => $crowdfunding,
                'form' => $form?->createView(),
            ]
        )->setMaxAge(3600);
    }
}
