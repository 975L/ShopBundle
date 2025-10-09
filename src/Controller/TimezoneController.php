<?php

namespace c975L\ShopBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TimezoneController extends AbstractController
{
    #[Route(
        '/set-timezone',
        name: 'set_timezone',
        methods: ['POST']
    )]
    public function setTimezone(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $timezone = $data['timezone'] ?? 'Europe/Paris';

        // Stores in session
        $request->getSession()->set('user_timezone', $timezone);

        return new JsonResponse(['success' => true]);
    }
}