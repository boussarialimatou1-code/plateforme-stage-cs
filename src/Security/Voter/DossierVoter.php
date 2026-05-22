<?php

/**
 * ================================================================================================
 * SECURITY VOTER : DOSSIER VOTER (DossierVoter)
 * ================================================================================================
 * 
 * FICHIER : src/Security/Voter/DossierVoter.php
 * 
 * ROLE PRINCIPAL :
 * Ce fichier implémente un système de contrôle d'accès basé sur des règles métier.
 * 
 * C'EST QUOI UN VOTER ?
 * Un Voter est une classe Symfony qui répond à la question :
 * "Cet utilisateur a-t-il le droit de faire cette action sur cet objet ?"
 * 
 * SYSTÈME DE VOTE :
 * Quand on appelle $this->denyAccessUnlessGranted('POST_VIEW', $dossier),
 * Symfony interroge TOUS les Voters enregistrés.
 * Chaque Voter répond :
 * - YES → L'utilisateur a le droit
 * - NO → L'utilisateur n'a pas le droit
 * - ABSTAIN → Le Voter ne se prononce pas (ce n'est pas son rôle)
 * 
 * RÈGLE DE DÉCISION :
 * - Si AU MOINS un Voter dit NO → ACCÈS REFUSÉ
 * - Si TOUS les Voters disent YES ou ABSTAIN → ACCÈS AUTORISÉ
 * 
 * --------------------------------------------------------------------------------
 * ATTRIBUTS GÉRÉS PAR CE VOTER :
 * --------------------------------------------------------------------------------
 * 
 * 1. POST_VIEW (self::VIEW)
 *    → Droit de voir un dossier
 *    → Qui peut voir ?
 *      - Admins et Évaluateurs → TOUJOURS OUI
 *      - Candidat → Seulement si c'est SON dossier
 * 
 * 2. POST_EDIT (self::EDIT)
 *    → Droit de modifier un dossier
 *    → Qui peut modifier ?
 *      - PERSONNE (pour l'instant)
 *      - Un dossier soumis ne peut plus être modifié
 * 
 * --------------------------------------------------------------------------------
 * POURQUOI UN VOTER PLUTÔT QUE isGranted('ROLE_X') ?
 * --------------------------------------------------------------------------------
 * 
 * isGranted('ROLE_ADMIN') → Vérifie juste le rôle
 * isGranted('POST_VIEW', $dossier) → Vérifie le rôle + la propriété de l'objet
 * 
 * EXEMPLE CONCRET :
 * 
 * // Avec isGranted() simple
 * if ($this->isGranted('ROLE_USER')) {
 *     // Tous les users peuvent voir TOUS les dossiers ❌
 * }
 * 
 * // Avec Voter
 * if ($this->isGranted('POST_VIEW', $dossier)) {
 *     // Seuls les admins + le propriétaire peuvent voir ✅
 * }
 * 
 * --------------------------------------------------------------------------------
 * DEPENDANCES :
 * --------------------------------------------------------------------------------
 */

namespace App\Security\Voter;

// Importe l'entité Dossier (l'objet sur lequel on vote)
use App\Entity\Dossier;

// Importe l'entité Utilisateur (l'utilisateur qui demande l'accès)
use App\Entity\Utilisateur;

// Importe les classes de base pour les Voters
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

// Importe Vote pour le typage (Symfony 7+)
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * ================================================================================================
 * CLASSE DossierVoter
 * ================================================================================================
 * 
 * Voter pour les dossiers de candidature.
 * 
 * HÉRITAGE :
 * Étend Voter, qui est la classe de base pour tous les voters Symfony.
 * 
 * MÉTHODES REQUISES :
 * - supports() → Déclare ce que ce Voter peut voter
 * - voteOnAttribute() → La logique de vote
 * 
 * MÉTHODES PERSONNALISÉES :
 * - canView() → Vérifie si l'utilisateur peut voir le dossier
 * - canEdit() → Vérifie si l'utilisateur peut modifier le dossier
 */
class DossierVoter extends Voter
{
    /**
     * ================================================================================================
     * CONSTANTES : ATTRIBUTS
     * ================================================================================================
     * 
     * Ces constantes définissent les "permissions" gérées par ce Voter.
     * 
     * UTILISATION :
     * 
     * // Dans un contrôleur
     * $this->denyAccessUnlessGranted(DossierVoter::VIEW, $dossier);
     * $this->denyAccessUnlessGranted(DossierVoter::EDIT, $dossier);
     * 
     * // Dans un template Twig
     * {% if is_granted('POST_VIEW', dossier) %}
     *     <a href="#">Voir le dossier</a>
     * {% endif %}
     */
    
    /**
     * Permission : Voir un dossier
     * Utilisé pour : Afficher le détail d'un dossier
     */
    public const VIEW = 'POST_VIEW';
    
    /**
     * Permission : Modifier un dossier
     * Utilisé pour : Éditer les informations d'un dossier
     * (Actuellement personne ne peut modifier)
     */
    public const EDIT = 'POST_EDIT';

    /**
     * ================================================================================================
     * METHODE : supports()
     * ================================================================================================
     * 
     * ROLE : Déclare ce que ce Voter peut voter
     * 
     * QUAND EST-ELLE APPELÉE ?
     * À CHAQUE fois qu'on appelle isGranted() ou denyAccessUnlessGranted(),
     * Symfony appelle supports() sur TOUS les Voters enregistrés.
     * 
     * PARAMÈTRES :
     * 
     * 1. string $attribute
     *    → La permission demandée
     *    → Ex: 'POST_VIEW', 'POST_EDIT', 'ROLE_ADMIN', etc.
     * 
     * 2. mixed $subject
     *    → L'objet sur lequel porte la permission
     *    → Ex: un objet Dossier
     *    → Peut être NULL pour certaines permissions (ex: ROLE_ADMIN)
     * 
     * TYPE DE RETOUR : bool
     *    → true → Ce Voter PEUT voter sur cette combinaison attribute/subject
     *    → false → Ce Voter ne se prononce pas (ABSTAIN)
     * 
     * LOGIQUE :
     * Ce Voter ne vote que si :
     * 1. L'attribute est POST_VIEW ou POST_EDIT
     * 2. ET le subject est un objet Dossier
     * 
     * @param string $attribute La permission demandée
     * @param mixed $subject L'objet sur lequel porte la permission
     * @return bool true si ce Voter peut voter, false sinon
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Vérifie 2 conditions :
        
        // 1. L'attribute est POST_VIEW ou POST_EDIT
        // in_array() vérifie si $attribute est dans le tableau
        $isAttributeValid = in_array($attribute, [self::VIEW, self::EDIT]);
        
        // 2. Le subject est un objet Dossier
        // instanceof vérifie le type de l'objet
        $isSubjectValid = $subject instanceof Dossier;
        
        // Retourne true si LES DEUX conditions sont vraies
        return $isAttributeValid && $isSubjectValid;
        
        // EXEMPLES :
        // supports('POST_VIEW', $dossier) → true ✅
        // supports('POST_EDIT', $dossier) → true ✅
        // supports('ROLE_ADMIN', $dossier) → false ❌ (mauvais attribute)
        // supports('POST_VIEW', $user) → false ❌ (mauvais subject)
    }

    /**
     * ================================================================================================
     * METHODE : voteOnAttribute()
     * ================================================================================================
     * 
     * ROLE : Détermine si l'utilisateur a le droit ou non
     * 
     * QUAND EST-ELLE APPELÉE ?
     * Uniquement si supports() a retourné true.
     * 
     * PARAMÈTRES :
     * 
     * 1. string $attribute
     *    → La permission demandée (POST_VIEW ou POST_EDIT)
     * 
     * 2. mixed $subject
     *    → L'objet Dossier sur lequel porte la permission
     * 
     * 3. TokenInterface $token
     *    → Le token d'authentification de l'utilisateur
     *    → Contient l'utilisateur connecté et ses rôles
     * 
     * 4. ?Vote $vote
     *    → Objet de vote (Symfony 7+)
     *    → Permet un vote plus nuancé (YES, NO, ABSTAIN)
     * 
     * TYPE DE RETOUR : bool
     *    → true → ACCÈS AUTORISÉ (YES)
     *    → false → ACCÈS REFUSÉ (NO)
     * 
     * LOGIQUE DE VOTE :
     * 
     * 1. Récupère l'utilisateur connecté
     * 2. Récupère ses rôles
     * 3. Si ROLE_EVALUATEUR ou ROLE_ADMIN → YES (toujours)
     * 4. Sinon, appelle la méthode spécifique (canView ou canEdit)
     * 
     * @param string $attribute La permission demandée
     * @param mixed $subject L'objet Dossier
     * @param TokenInterface $token Le token d'authentification
     * @param ?Vote $vote L'objet de vote
     * @return bool true si accès autorisé, false sinon
     */
    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool {
        // --------------------------------------------------------------------------------
        // ETAPE 1 : RÉCUPÉRER L'UTILISATEUR CONNECTÉ
        // --------------------------------------------------------------------------------
        
        // getUser() extrait l'utilisateur du token d'authentification
        // Si personne n'est connecté, retourne null
        $user = $token->getUser();

        // --------------------------------------------------------------------------------
        // ETAPE 2 : VÉRIFIER LES RÔLES DE L'UTILISATEUR
        // --------------------------------------------------------------------------------
        
        // Récupère les rôles de l'utilisateur
        // Si user est null, retourne un tableau vide
        $roles = $user?->getRoles() ?? [];
        
        // --------------------------------------------------------------------------------
        // CAS 1 : L'UTILISATEUR EST ADMIN OU ÉVALUATEUR
        // --------------------------------------------------------------------------------
        
        // Si l'utilisateur a ROLE_EVALUATEUR ou ROLE_ADMIN
        // Il a TOUJOURS le droit, peu importe le dossier
        if (in_array('ROLE_EVALUATEUR', $roles) || in_array('ROLE_ADMIN', $roles)) {
            return true;  // YES → Accès autorisé
        }
        
        // --------------------------------------------------------------------------------
        // CAS 2 : L'UTILISATEUR EST UN CANDIDAT (ou autre)
        // --------------------------------------------------------------------------------
        
        // Si on arrive ici, l'utilisateur n'est ni admin ni évaluateur
        // C'est probablement un candidat
        // On vérifie alors s'il est propriétaire du dossier
        
        /** @var Dossier $dossier */
        $dossier = $subject;  // Cast explicite pour l'IDE

        // Appelle la méthode appropriée selon l'attribute
        return match($attribute) {
            // Permission : Voir le dossier
            self::VIEW => $this->canView($dossier, $user),
            
            // Permission : Modifier le dossier
            self::EDIT => $this->canEdit($dossier, $user),
            
            // Par défaut : refus
            default => false,
        };
    }

    /**
     * ================================================================================================
     * METHODE : canView()
     * ================================================================================================
     * 
     * ROLE : Vérifie si l'utilisateur peut voir le dossier
     * 
     * PARAMÈTRES :
     * 
     * 1. Dossier $dossier
     *    → Le dossier à voir
     * 
     * 2. ?UserInterface $user
     *    → L'utilisateur qui demande l'accès
     *    → Peut être null si personne n'est connecté
     * 
     * TYPE DE RETOUR : bool
     *    → true → Peut voir
     *    → false → Ne peut pas voir
     * 
     * RÈGLE MÉTIER :
     * Un candidat ne peut voir QUE son propre dossier.
     * 
     * @param Dossier $dossier Le dossier à voir
     * @param ?UserInterface $user L'utilisateur
     * @return bool true si l'utilisateur peut voir le dossier
     */
    private function canView(Dossier $dossier, ?UserInterface $user): bool
    {
        // --------------------------------------------------------------------------------
        // CAS 1 : L'UTILISATEUR EST CONNECTÉ ET EST UN CANDIDAT
        // --------------------------------------------------------------------------------

        // Vérifie si $user est une instance de notre classe Utilisateur
        if ($user instanceof Utilisateur) {
            // Compare le propriétaire du dossier avec l'utilisateur connecté
            // getCandidat() retourne l'objet Utilisateur qui a créé le dossier
            // Si c'est le même utilisateur → OUI, il peut voir
            return $dossier->getCandidat() === $user;
        }

        // --------------------------------------------------------------------------------
        // CAS 2 : L'UTILISATEUR N'EST PAS CONNECTÉ
        // --------------------------------------------------------------------------------

        // Si on arrive ici, l'utilisateur n'est pas connecté
        // ou n'est pas une instance de Utilisateur (ex: utilisateur anonyme)
        // → NON, il ne peut pas voir
        return false;
    }

    /**
     * ================================================================================================
     * METHODE : canEdit()
     * ================================================================================================
     * 
     * ROLE : Vérifie si l'utilisateur peut modifier le dossier
     * 
     * PARAMÈTRES :
     * 
     * 1. Dossier $dossier
     *    → Le dossier à modifier
     * 
     * 2. ?UserInterface $user
     *    → L'utilisateur qui demande l'accès
     * 
     * TYPE DE RETOUR : bool
     *    → true → Peut modifier
     *    → false → Ne peut pas modifier
     * 
     * RÈGLE MÉTIER ACTUELLE :
     * PERSONNE ne peut modifier un dossier après soumission.
     * 
     * POURQUOI ?
     * - Intégrité des données
     * - Traçabilité des candidatures
     * - Évite la fraude après soumission
     * 
     * ÉVOLUTION FUTURE :
     * On pourrait permettre la modification :
     * - Uniquement si le dossier est EN_ATTENTE
     * - Uniquement par le propriétaire
     * - Avant une date limite
     * 
     * @param Dossier $dossier Le dossier à modifier
     * @param ?UserInterface $user L'utilisateur
     * @return bool true si l'utilisateur peut modifier le dossier
     */
    private function canEdit(Dossier $dossier, ?UserInterface $user): bool
    {
        // Actuellement, personne ne peut modifier un dossier
        // Retourne toujours false
        return false;
        
        // EXEMPLE DE LOGIQUE FUTURE (commentée) :
        // 
        // // Seul le propriétaire peut modifier
        // if (!$user instanceof User || $dossier->getUser() !== $user) {
        //     return false;
        // }
        // 
        // // Seulement si le dossier est en attente
        // if ($dossier->getStatut() !== DossierStatus::EN_ATTENTE) {
        //     return false;
        // }
        // 
        // // Seulement avant la date limite
        // $deadline = new \DateTime('2026-12-31');
        // if (new \DateTime() > $deadline) {
        //     return false;
        // }
        // 
        // return true;
    }
}
