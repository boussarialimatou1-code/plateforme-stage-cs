<?php

namespace App\Controller\Admin;

use App\Entity\Evaluateur;
use App\Entity\Admin;
use App\Enum\StatutDossier;
use App\Enum\TypeStructure;
use App\Repository\DossierRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur du tableau de bord évaluateur.
 */
#[Route('/admin')]
class DashboardController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[Route('/', name: 'app_admin_dashboard')]
    public function index(DossierRepository $dossierRepository): Response
    {
        // Accès réservé aux évaluateurs uniquement
        if (!$this->isGranted('ROLE_EVALUATEUR')) {
            throw $this->createAccessDeniedException('Accès réservé aux évaluateurs uniquement.');
        }

        /** @var Evaluateur|Admin|null  */
        $user = $this->getUser();

        // Si pas d'utilisateur connecté
        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        // Si l'utilisateur est un Admin mais pas un Evaluateur, rediriger
        if ($user instanceof Admin && !$user instanceof Evaluateur) {
            $this->logger->info('Admin redirigé depuis le dashboard évaluateur', [
                'user_email' => $user->getEmail(),
            ]);
            return $this->redirectToRoute('app_admin_users_list');
        }

        // Vérifier que l'utilisateur est bien un Evaluateur
        if (!$user instanceof Evaluateur) {
            $this->logger->error('Utilisateur non-évaluateur tente d\'accéder au dashboard', [
                'user_class' => \get_class($user),
            ]);
            throw $this->createAccessDeniedException('Accès non autorisé.');
        }

        $isMain = $user->isMainEvaluator();

        $criteria = [];
        if (!$isMain) {
            $criteria['evaluateur'] = [$user, null];
        }

        $en_attente = $dossierRepository->count([...$criteria, 'statut' => StatutDossier::EN_ATTENTE]);
        $en_evaluation = $dossierRepository->count([...$criteria, 'statut' => StatutDossier::EN_EVALUATION]);
        $mis_en_reserve = $dossierRepository->count([...$criteria, 'statut' => StatutDossier::MIS_EN_RESERVE]);
        $valide = $dossierRepository->count([...$criteria, 'statut' => StatutDossier::VALIDE]);
        $refuse = $dossierRepository->count([...$criteria, 'statut' => StatutDossier::REJETE]);

        $total_dossiers = $en_attente + $en_evaluation + $mis_en_reserve + $valide + $refuse;

        $stages_actifs = $dossierRepository->findBy(
            array_merge($criteria, ['statut' => StatutDossier::VALIDE]),
            ['dateCreation' => 'DESC']
        );
        $dossier_par_structure_raw = $dossierRepository->findDossierParStructure();

        $dossier_par_structure = [];
        foreach (TypeStructure::cases() as $item) {
            $dossier_par_structure[] = [
                'structure' => $item->getLabel(),
                'value' => $item->value,
                'count' => $dossier_par_structure_raw[$item->value] ?? 0,
            ];
        }

        return $this->render('admin/dashboard/stats.html.twig', [
            'total_dossiers' => $total_dossiers,
            'en_attente' => $en_attente,
            'en_evaluation' => $en_evaluation,
            'mis_en_reserve' => $mis_en_reserve,
            'valide' => $valide,
            'refuse' => $refuse,
            'stages_actifs' => $stages_actifs,
            'dossier_par_structure' => $dossier_par_structure,
        ]);
    }
}
