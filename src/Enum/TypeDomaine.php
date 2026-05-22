<?php declare(strict_types=1);

namespace App\Enum;

enum TypeDomaine: string
{
    case INFORMATIQUE = 'Informatique';
    case DROIT_JURIDIQUE = 'Droit / Juridique';
    case RESSOURCES_HUMAINES = 'Ressources Humaines';
    case FINANCES_COMPTABILITE = 'Finances / Comptabilité';
    case SECRETARIAT_ADMINISTRATION = 'Secrétariat / Administration';
    case COMMUNICATION = 'Communication';
    case GREFFE = 'Greffe';

    public function getLabel(): string
    {
        return match($this) {
            self::INFORMATIQUE => 'Informatique',
            self::DROIT_JURIDIQUE => 'Droit / Juridique',
            self::RESSOURCES_HUMAINES => 'Ressources Humaines',
            self::FINANCES_COMPTABILITE => 'Finances / Comptabilité',
            self::SECRETARIAT_ADMINISTRATION => 'Secrétariat / Administration',
            self::COMMUNICATION => 'Communication',
            self::GREFFE => 'Greffe',
        };
    }
} 
