<?php

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Basket;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailService implements EmailServiceInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function send(Basket $basket)
    {
        // Defines adresses and names
        $from = $this->configService->getParameter('c975LShop.from');
        $fromName = $this->configService->hasParameter('c975LShop.fromName') ? $this->configService->getParameter('c975LShop.fromName') : '';
        $bcc = $this->configService->getParameter('c975LShop.replyTo');
        $bccName = $this->configService->hasParameter('c975LShop.replyToName') ? $this->configService->getParameter('c975LShop.replyToName') : '';
        $replyTo = $this->configService->getParameter('c975LShop.replyTo');
        $replyToName = $this->configService->hasParameter('c975LShop.replyToName') ? $this->configService->getParameter('c975LShop.replyToName') : '';

        // Creates email
        $email = new TemplatedEmail();
        $email->from(new Address($from, $fromName));
        $email->to(new Address($basket->getEmail()));
        $email->bcc(new Address($bcc, $bccName));
        $email->replyTo(new Address($replyTo, $replyToName));
        $email->subject($this->translator->trans('label.order_confirmation', [], 'shop'));
        $email->htmlTemplate('@c975LShop/emails/basket.html.twig');
        $email->context([
            'basket' => $basket,
        ]);

        $this->mailer->send($email);
    }
}
