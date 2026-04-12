<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

final class HomeController
{
    public function index(): Response
    {
        $template = dirname(__DIR__, 2) . '/templates/home.html.twig';
        $html = (string) file_get_contents($template);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
