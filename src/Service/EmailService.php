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

    // Retrieves the email configuration
    public function getEmailConfig(): array
    {
        return [
            'from' => $this->configService->getParameter('c975LShop.from'),
            'fromName' => $this->configService->hasParameter('c975LShop.fromName') ? $this->configService->getParameter('c975LShop.fromName') : '',
            'replyTo' => $this->configService->getParameter('c975LShop.replyTo'),
            'replyToName' => $this->configService->hasParameter('c975LShop.replyToName') ? $this->configService->getParameter('c975LShop.replyToName') : '',
            'bcc' => $this->configService->getParameter('c975LShop.replyTo'),
            'bccName' => $this->configService->hasParameter('c975LShop.replyToName') ? $this->configService->getParameter('c975LShop.replyToName') : '',
        ];
    }

    // Creates a new email
    public function create(): TemplatedEmail
    {
        $data = $this->getEmailConfig();

        $email = new TemplatedEmail();
        $email->from(new Address($data['from'], $data['fromName']));
        $email->bcc(new Address($data['bcc'], $data['bccName']));
        $email->replyTo(new Address($data['replyTo'], $data['replyToName']));

        return $email;
    }

    // Sends the email
    public function send($email)
    {
        $this->mailer->send($email);
    }

    // Sends the order confirmation email
    public function sendOrderConfirmation(Basket $basket)
    {
        $email = $this->create();
        $email->to(new Address($basket->getEmail()));
        $email->subject($this->translator->trans('label.order_confirmation', [], 'shop'));
        $email->htmlTemplate('@c975LShop/emails/order_confirmation.html.twig');
        $email->context([
            'basket' => $basket,
        ]);

        $this->send($email);
    }

    // Sends the download information email
    public function sendDownloadInformation($basket, array $downloadLinks): void
    {
        $email = $this->create();
        $email->to(new Address($basket->getEmail()));
        $email->subject($this->translator->trans('label.download_information', [], 'shop'));
        $email->htmlTemplate('@c975LShop/emails/download_information.html.twig');
        $email->context([
            'basket' => $basket,
            'downloadLinks' => $downloadLinks,
            'expirationDays' => 7,
        ]);

        $this->send($email);
    }
}
