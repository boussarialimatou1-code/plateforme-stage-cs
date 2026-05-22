<?php declare(strict_types=1);

namespace App\Enum;

enum TypeStructure: string
{
    case SECRETARIAT_GENERALE = 'secretariat_general';
    case GREFFE_CENTRALE = 'greffe_central';
    case PAQUET_GENERALE = 'paquet_general';
    case CHAMBRE_ADMINISTRATIVE = 'chambre_administrative';
    case CHAMBRE_JUDICIAIRE = 'chambre_judiciaire';
    case CABINET = 'cabinet';


    public function getLabel(): string
    {
        return match ($this) {
            self::SECRETARIAT_GENERALE => 'Sécrétariat Général',
            self::GREFFE_CENTRALE => 'Greffe  Central',
            self::PAQUET_GENERALE => 'Paquet Général',
            self::CHAMBRE_ADMINISTRATIVE => 'Chambre Administratives',
            self::CHAMBRE_JUDICIAIRE => 'Chambre Judiciaire',
            self::CABINET => 'Cabinet',
        };
    }

}
