<?php

namespace App\Enum;

enum TypeDocument: string {
    case CV = 'Curriculum Vitae (CV)';
    case LETTRE_MOTIVATION = 'Lettre de Motivation';
    case PIECE_IDENTITE = "Pièce d'identité";
    case PHOTO_IDENTITE = "Photo d'identité";
    case LETTRE_RECOMMANDATION = 'Lettre de recommandation';
    case LETTRE_RENOUVELLEMENT = 'Lettre de demande de renouvellement';
}


