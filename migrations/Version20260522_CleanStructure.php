<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour nettoyer les valeurs invalides de la colonne structure
 * Les valeurs vides ou invalides sont remplacées par NULL
 */
final class Version20260522_CleanStructure extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean invalid structure values in dossier table';
    }

    public function up(Schema $schema): void
    {
        // Valeurs valides d'énumération
        $validStructures = [
            'secretariat_general',
            'greffe_central',
            'paquet_general',
            'chambre_administrative',
            'chambre_judiciaire',
            'cabinet'
        ];
        
        $placeholders = implode(',', array_fill(0, count($validStructures), '?'));
        
        // Remplacer les valeurs invalides par NULL
        $this->addSql(
            "UPDATE dossier SET structure = NULL WHERE structure NOT IN ($placeholders) OR structure = ''",
            $validStructures
        );
    }

    public function down(Schema $schema): void
    {
        // Non-reversible - les données invalides sont perdues
    }
}
