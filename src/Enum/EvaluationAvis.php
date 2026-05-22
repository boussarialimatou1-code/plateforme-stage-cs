<?php

/**
 * ================================================================================================
 * ÉNUMÉRATION : AVIS D'ÉVALUATION (EvaluationAvis)
 * ================================================================================================
 * 
 * FICHIER : src/Enum/EvaluationAvis.php
 * 
 * ROLE PRINCIPAL :
 * Cette énumération définit les 3 avis possibles qu'un évaluateur peut donner
 * sur un dossier de candidature.
 * 
 * --------------------------------------------------------------------------------
 * LES 3 AVIS POSSIBLES :
 * --------------------------------------------------------------------------------
 * 
 * 1. FAVORABLE → Le candidat est admis directement
 * 2. DEFAVORABLE → Le candidat est refusé directement
 * 3. RESERVE → Le dossier nécessite une décision complémentaire de l'admin
 * 
 * --------------------------------------------------------------------------------
 * IMPACT SUR LE STATUT DU DOSSIER :
 * --------------------------------------------------------------------------------
 * 
 * Quand un évaluateur soumet un avis, le statut du dossier change AUTOMATIQUEMENT :
 * 
 * | Avis donné      | Nouveau statut du dossier | Action suivante            |
 * |-----------------|---------------------------|----------------------------|
 * | FAVORABLE       | VALIDE                    | Stage activé               |
 * | DEFAVORABLE     | REFUSE                    | Email de refus envoyé      |
 * | RESERVE         | MIS_EN_RESERVE            | Attend décision admin      |
 * 
 * --------------------------------------------------------------------------------
 * UTILISATION DANS LE CODE :
 * --------------------------------------------------------------------------------
 * 
 * // Dans un formulaire (EvaluationType)
 * ChoiceType::class, [
 *     'choices' => EvaluationAvis::cases(),  // Tous les avis possibles
 * ]
 * 
 * // Comparer un avis
 * if ($evaluation->getAvis() === EvaluationAvis::FAVORABLE) { ... }
 * 
 * // Dans un template Twig
 * {{ evaluation.avis.value }}       → "favorable"
 * {{ evaluation.avis.getLabel() }}  → "Favorable (Admission directe)"
 * {{ evaluation.avis.getColor() }}  → "#16a34a" (vert)
 * 
 * --------------------------------------------------------------------------------
 * DEPENDANCES :
 * --------------------------------------------------------------------------------
 */

namespace App\Enum;

/**
 * ================================================================================================
 * ENUM EvaluationAvis
 * ================================================================================================
 * 
 * Représente les 3 avis possibles qu'un évaluateur peut donner sur un dossier.
 * 
 * @property string $value La valeur string stockée en BDD
 * @method string getLabel() Retourne le label lisible pour l'affichage
 * @method string getColor() Retourne le code couleur hexadécimal pour l'UI
 */
enum EvaluationAvis: string
{
    /**
     * --------------------------------------------------------------------------------
     * CAS 1 : FAVORABLE
     * --------------------------------------------------------------------------------
     * 
     * Signification : L'évaluateur recommande l'admission du candidat
     * 
     * CONSÉQUENCE :
     * - Le dossier passe automatiquement au statut VALIDE
     * - Le stage est activé avec une date de début
     * - Un email de confirmation est envoyé au candidat
     * 
     * COULEUR ASSOCIÉE : Vert (#16a34a)
     * - Symbolise la validation, le succès, le feu vert
     */
    case FAVORABLE = 'favorable';
    
    /**
     * --------------------------------------------------------------------------------
     * CAS 2 : RESERVE
     * --------------------------------------------------------------------------------
     * 
     * Signification : L'évaluateur a un avis mitigé, ni totalement favorable ni défavorable
     * 
     * RAISONS POSSIBLES D'UN AVIS EN RÉSERVE :
     * - Le dossier est bon mais incomplet
     * - Le profil correspond partiellement au stage
     * - L'évaluateur veut un second avis
     * - Des informations complémentaires sont nécessaires
     * 
     * CONSÉQUENCE :
     * - Le dossier passe au statut MIS_EN_RESERVE
     * - L'administrateur doit prendre la décision finale
     * - Le candidat reste en attente
     * 
     * COULEUR ASSOCIÉE : Orange (#f59e0b)
     * - Symbolise l'attente, la prudence, le feu orange
     */
    case RESERVE = 'reserve';
    
    /**
     * --------------------------------------------------------------------------------
     * CAS 3 : DEFAVORABLE
     * --------------------------------------------------------------------------------
     * 
     * Signification : L'évaluateur ne recommande pas l'admission du candidat
     * 
     * RAISONS POSSIBLES D'UN AVIS DÉFAVORABLE :
     * - Le dossier est incomplet
     * - Le profil ne correspond pas au stage demandé
     * - Les notes/compétences sont insuffisantes
     * - Places limitées, priorité à d'autres candidats
     * 
     * CONSÉQUENCE :
     * - Le dossier passe automatiquement au statut REFUSE
     * - Un email de refus est envoyé au candidat
     * - Le processus est terminé pour ce candidat
     * 
     * COULEUR ASSOCIÉE : Rouge (#dc2626)
     * - Symbolise le refus, l'interdiction, le feu rouge
     */
    case DEFAVORABLE = 'defavorable';

    /**
     * ================================================================================================
     * METHODE : getLabel()
     * ================================================================================================
     * 
     * ROLE : Retourne une version lisible et descriptive de l'avis pour l'affichage
     * 
     * UTILISATION :
     * - Dans les formulaires d'évaluation (radio buttons)
     * - Dans les templates Twig pour afficher l'avis
     * - Dans les emails de notification
     * 
     * EXEMPLES D'UTILISATION :
     * 
     * echo EvaluationAvis::FAVORABLE->getLabel();
     * // Retourne : "Favorable (Admission directe)"
     * 
     * // Dans Twig :
     * {{ evaluation.avis.getLabel() }}
     * 
     * @return string Le label lisible de l'avis
     */
    public function getLabel(): string
    {
        // match() retourne une chaîne descriptive pour chaque avis
        // Chaque label inclut une description entre parenthèses pour plus de clarté
        return match($this) {
            // FAVORABLE est affiché avec "(Admission directe)" pour être explicite
            self::FAVORABLE => 'Favorable (Admission directe)',
            
            // RESERVE est affiché avec "(Décision ultérieure)" pour indiquer l'attente
            self::RESERVE => 'Mis en Réserve (Décision ultérieure)',
            
            // DEFAVORABLE est affiché avec "(Refus direct)" pour être clair
            self::DEFAVORABLE => 'Défavorable (Refus direct)',
        };
    }

    /**
     * ================================================================================================
     * METHODE : getColor()
     * ================================================================================================
     * 
     * ROLE : Retourne le code couleur hexadécimal associé à chaque avis
     * 
     * UTILISATION :
     * - Dans les templates Twig pour colorer les badges d'avis
     * - Pour créer des indicateurs visuels dans l'interface
     * - Pour les graphiques et statistiques
     * 
     * CODES COULEUR :
     * - Vert (#16a34a) → FAVORABLE (positif)
     * - Orange (#f59e0b) → RESERVE (attention/attente)
     * - Rouge (#dc2626) → DEFAVORABLE (négatif)
     * 
     * EXEMPLES D'UTILISATION :
     * 
     * echo EvaluationAvis::FAVORABLE->getColor();
     * // Retourne : "#16a34a"
     * 
     * // Dans Twig :
     * <span style="color: {{ evaluation.avis.getColor() }}">
     * 
     * @return string Le code couleur hexadécimal (format CSS)
     */
    public function getColor(): string
    {
        // match() retourne un code couleur hexadécimal pour chaque avis
        return match($this) {
            // Vert pour FAVORABLE → couleur positive, validation
            self::FAVORABLE => '#16a34a',
            
            // Orange pour RESERVE → couleur d'attente, prudence
            self::RESERVE => '#f59e0b',
            
            // Rouge pour DEFAVORABLE → couleur négative, refus
            self::DEFAVORABLE => '#dc2626',
        };
    }
}
