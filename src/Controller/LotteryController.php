<?php

namespace c975L\ShopBundle\Controller;

use c975L\ShopBundle\Entity\Lottery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use c975L\ShopBundle\Service\LotteryServiceInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LotteryController extends AbstractController
{
    public function __construct(
        private readonly LotteryServiceInterface $lotteryService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    // DISPLAY
    #[Route(
        '/shop/lottery/{identifier:lottery}',
        name: 'lottery_display',
        requirements: ['identifier' => '^([a-zA-Z0-9\-]{13})'],
        methods: ['GET']
    )]
    public function display(Lottery $lottery): Response
    {
        return $this->render('@c975LShop/lottery/display.html.twig', [
            'lottery' => $lottery,
        ]);
    }

    // API endpoint to draw a winner for a specific prize
    #[Route(
        '/shop/lottery/{identifier:lottery}/draw/{rank}',
        name: 'lottery_draw_prize',
        requirements: [
            'identifier' => '^([a-zA-Z0-9\-]{13})',
            'rank' => '^[0-5]$'
        ],
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN')]
    public function drawPrize(Lottery $lottery, int $rank): JsonResponse
    {
        if (false === $lottery->isActive()) {
            return new JsonResponse(['error' => 'Lottery not active'], Response::HTTP_BAD_REQUEST);
        }

        // Defines winning ticket for specified rank
        $winningTicket = $this->lotteryService->drawWinner($lottery, $rank);
        if (!$winningTicket) {
            return new JsonResponse(['error' => 'No eligible tickets found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'number' => $winningTicket->getNumber(),
            'name' => $winningTicket->getContributor()->getName(),
        ]);
    }
}