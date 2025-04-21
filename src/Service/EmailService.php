<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ShopBundle\Service;

use c975L\ShopBundle\Entity\Basket;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailService implements EmailServiceInterface
{
    private string $subjectPrefix;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly BodyRendererInterface $bodyRenderer,

    ) {
        $this->subjectPrefix = $this->translator->trans('label.shop', [], 'shop') . ' ' . $this->configService->getParameter('c975LShop.name') . ' - ';
    }

    // Retrieves the email configuration
    public function getConfig(): array
    {
        return [
            'name' => $this->configService->getParameter('c975LShop.name'),
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
        $data = $this->getConfig();

        $email = new TemplatedEmail();
       $email->from(new Address($data['from'], $data['fromName']));
       $email->bcc(new Address($data['bcc'], $data['bccName']));
       $email->replyTo(new Address($data['replyTo'], $data['replyToName']));

        return $email;
    }

    // Sends the email
    public function send($email)
    {
        // To display the message in place of sending (for debug)
        // $this->bodyRenderer->render($email);
        // echo $email->getHtmlBody();
        $this->mailer->send($email);
    }

    // Sends the order confirmation email
    public function confirmOrder(Basket $basket)
    {
        $email = $this->create();
       $email->to(new Address($basket->getEmail()));
        $email->subject($this->subjectPrefix . $this->translator->trans('label.confirm_order', [], 'shop'));
        $email->htmlTemplate('@c975LShop/emails/confirm_order.html.twig');
        $email->context([
            'basket' => $basket,
        ]);

        $this->send($email);
    }

    // Sends the download information email
    public function downloadInformation(Basket $basket, array $downloadLinks): void
    {
        $email = $this->create();
       $email->to(new Address($basket->getEmail()));
        $email->subject($this->subjectPrefix . $this->translator->trans('label.download_information', [], 'shop'));
        $email->htmlTemplate('@c975LShop/emails/download_information.html.twig');
        $email->context([
            'basket' => $basket,
            'downloadLinks' => $downloadLinks,
            'expirationDays' => 7,
        ]);

        $this->send($email);
    }

    // Sends the crowdfunding contribution email
    public function crowdfundingContribution(Basket $basket, array $counterparts): void
    {
        $email = $this->create();
       $email->to(new Address($basket->getEmail()));
        $email->subject($this->subjectPrefix . $this->translator->trans('label.crowdfunding_contribution', [], 'shop'));
        $email->htmlTemplate('@c975LShop/emails/crowdfunding_contribution.html.twig');
        $email->context([
            'basket' => $basket,
            'counterparts' => $counterparts,
        ]);

        $this->send($email);
    }

    // Sends the items shipped email
    public function shippedItems(Basket $basket, string $type): void
    {
        $subject = 'product' === $type ? 'label.items_shipped' : 'label.counterparts_shipped';
        $template = 'product' === $type ? 'items_shipped' : 'counterparts_shipped';
        $email = $this->create();
        $email->to(new Address($basket->getEmail()));
        $email->subject($this->subjectPrefix . $this->translator->trans($subject, [], 'shop'));
        $email->htmlTemplate('@c975LShop/emails/' . $template . '.html.twig');
        $email->context([
            'basket' => $basket,
        ]);

        $this->send($email);
    }

    // Sends the Stripe error message email
    public function stripeErrorMessage(Basket $basket, array $context): void
    {
        $email = $this->create();
        $email->to(new Address($this->configService->getParameter('c975LShop.replyTo')));
        $email->subject($this->subjectPrefix . 'Stripe Error !');
        $email->htmlTemplate('@c975LShop/emails/stripe_error.html.twig');
        $email->context([
            'basket' => $basket,
            'context' => $context,
        ]);

        $this->send($email);
    }
}
