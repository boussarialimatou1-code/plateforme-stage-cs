<?php

namespace App\Enum;

enum StatutDossier: string
{
    case EN_ATTENTE = 'En attente';
    case EN_EVALUATION = 'En évaluation';
    case MIS_EN_RESERVE = 'Mis en réserve';
    case ACCEPTE = 'Accepté';      // ✅ NOUVEAU : Validé par l'évaluateur mais lettre pas encore finalisée
    case VALIDE = 'Validé';         // Lettre officielle finalisée (signée + numérotée)
    case REJETE = 'Rejeté';

    public function getLabel(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'En attente',
            self::EN_EVALUATION => 'En évaluation',
            self::MIS_EN_RESERVE => 'Mis en réserve',
            self::ACCEPTE => 'Accepté',      // ✅ NOUVEAU
            self::VALIDE => 'Validé',
            self::REJETE => 'Rejeté',
        };
    }

    public function getCssClass(): string
    {
        return match($this) {
            self::EN_ATTENTE => 'status-pending',
            self::EN_EVALUATION => 'status-processing',
            self::MIS_EN_RESERVE => 'status-warning',
            self::ACCEPTE => 'status-info',         // ✅ NOUVEAU
            self::VALIDE => 'status-approved',
            self::REJETE => 'status-rejected',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::EN_ATTENTE => '#f59e0b',      // orange
            self::EN_EVALUATION => '#3b82f6',    // bleu
            self::MIS_EN_RESERVE => '#8b5cf6',   // violet
            self::ACCEPTE => '#06b6d4',          // ✅ cyan
            self::VALIDE => '#16a34a',           // vert
            self::REJETE => '#dc2626',           // rouge
        };
    }

    public static function tryFromLabel(string $label): ?self
    {
        foreach (self::cases() as $statut) {
            if ($statut->value === $label) {
                return $statut;
            }
        }
        return null;
    }
}