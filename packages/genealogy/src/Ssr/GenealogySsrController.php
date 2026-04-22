<?php

declare(strict_types=1);

namespace Waaseyaa\Genealogy\Ssr;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\GateInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Genealogy\Entity\GenealogyFamily;
use Waaseyaa\Genealogy\Service\GenealogyFamilyService;
use Waaseyaa\Genealogy\Service\GenealogyPedigreeService;
use Waaseyaa\User\User;

final class GenealogySsrController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
        private readonly GateInterface $gate,
        private readonly GenealogyPedigreeService $pedigree,
        private readonly GenealogyFamilyService $familyService,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function person(array $params, array $query, AccountInterface $account, Request $request): Response
    {
        if (!$this->genealogyProductEnabled($account)) {
            return new Response('Not Found', 404);
        }

        $id = isset($params['id']) ? (string) $params['id'] : '';
        $person = $this->pedigree->loadPerson($id);
        if ($person === null || !$this->gate->allows('view', $person, $account)) {
            return new Response('Not Found', 404);
        }

        $html = $this->twig->render('genealogy_person.html.twig', [
            'person' => $person,
            'parent_neighbors' => $this->pedigree->neighborSlots($this->pedigree->parentPersonIds($id), $account, $this->gate),
            'child_neighbors' => $this->pedigree->neighborSlots($this->pedigree->childPersonIds($id), $account, $this->gate),
            'spouse_neighbors' => $this->pedigree->neighborSlots($this->pedigree->spousePersonIds($id), $account, $this->gate),
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function family(array $params, array $query, AccountInterface $account, Request $request): Response
    {
        if (!$this->genealogyProductEnabled($account)) {
            return new Response('Not Found', 404);
        }

        $id = isset($params['id']) ? (string) $params['id'] : '';
        $family = $this->entityTypeManager->getStorage('genealogy_family')->load($id);
        if (!$family instanceof GenealogyFamily || !$this->gate->allows('view', $family, $account)) {
            return new Response('Not Found', 404);
        }

        $html = $this->twig->render('genealogy_family.html.twig', [
            'family' => $family,
            'member_neighbors' => $this->pedigree->neighborSlots($this->familyService->memberPersonIds($id), $account, $this->gate),
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ancestorChart(array $params, array $query, AccountInterface $account, Request $request): Response
    {
        if (!$this->genealogyProductEnabled($account)) {
            return new Response('Not Found', 404);
        }

        $id = isset($params['id']) ? (string) $params['id'] : '';
        $person = $this->pedigree->loadPerson($id);
        if ($person === null || !$this->gate->allows('view', $person, $account)) {
            return new Response('Not Found', 404);
        }

        $html = $this->twig->render('genealogy_ancestor_chart.html.twig', [
            'person' => $person,
            'generations' => $this->pedigree->ancestorGenerationsRedacted($id, $account, $this->gate),
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function genealogyProductEnabled(AccountInterface $account): bool
    {
        if (!$account->isAuthenticated()) {
            // Anonymous SSR is allowed only when the genealogy access policy grants
            // published tree + entity visibility (see GenealogyContentAccessPolicy).
            return true;
        }
        if (!$account instanceof User) {
            return false;
        }

        $v = $account->get('genealogy_product_enabled');

        return $v === true || $v === 1 || $v === '1';
    }
}
