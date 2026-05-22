<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Empêche le site d'être affiché dans une iframe (protection contre le Clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Empêche le navigateur de deviner le type de contenu (protection contre le reniflage de MIME)
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Active la protection XSS du navigateur
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Force l'utilisation du HTTPS (à activer seulement avec un certificat SSL valide)
        // $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        
        // Politique de sécurité du contenu (CSP) - Permet de restreindre d'où viennent les scripts et styles
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self';");
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
