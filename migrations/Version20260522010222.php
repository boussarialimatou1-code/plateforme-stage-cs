<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260522010222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_config (id INT AUTO_INCREMENT NOT NULL, setting_key VARCHAR(190) NOT NULL, setting_value LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_318942FC5FA1E697 (setting_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, nom_original VARCHAR(255) NOT NULL, chemin_fichier VARCHAR(255) NOT NULL, date_ajout DATETIME NOT NULL, type VARCHAR(255) NOT NULL, dossier_id INT NOT NULL, INDEX IDX_D8698A76611C0C56 (dossier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dossier (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, reference VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, type_stage VARCHAR(50) NOT NULL, domaine VARCHAR(255) NOT NULL, structure VARCHAR(255) NOT NULL, duree_mois INT NOT NULL, date_debut_stage DATETIME DEFAULT NULL, date_fin_stage DATETIME DEFAULT NULL, statut VARCHAR(50) NOT NULL, lettre_finalisee TINYINT DEFAULT 0 NOT NULL, is_renouvellement TINYINT DEFAULT 0 NOT NULL, numero_officiel VARCHAR(50) DEFAULT NULL, signature_officielle LONGTEXT DEFAULT NULL, candidat_id INT NOT NULL, evaluateur_id INT DEFAULT NULL, parent_dossier_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_3D48E037AEA34913 (reference), INDEX IDX_3D48E0378D0EB82 (candidat_id), INDEX IDX_3D48E037231F139 (evaluateur_id), INDEX IDX_3D48E037265F8806 (parent_dossier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evaluation (id INT AUTO_INCREMENT NOT NULL, avis VARCHAR(255) NOT NULL, commentaire LONGTEXT DEFAULT NULL, date_evaluation DATETIME NOT NULL, dossier_id INT NOT NULL, evaluateur_id INT DEFAULT NULL, INDEX IDX_1323A575611C0C56 (dossier_id), INDEX IDX_1323A575231F139 (evaluateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, date_envoi DATETIME NOT NULL, lu TINYINT NOT NULL, receveur_id INT NOT NULL, INDEX IDX_BF5476CAB967E626 (receveur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, telephone VARCHAR(20) NOT NULL, sexe VARCHAR(10) DEFAULT NULL, dob DATETIME DEFAULT NULL, etablissement VARCHAR(255) DEFAULT NULL, niveau VARCHAR(255) DEFAULT NULL, filiere VARCHAR(255) DEFAULT NULL, doit_changer_mot_de_passe TINYINT DEFAULT 0 NOT NULL, reset_token VARCHAR(100) DEFAULT NULL, reset_token_expires_at DATETIME DEFAULT NULL, can_manage_admins TINYINT DEFAULT 0 NOT NULL, is_main_evaluator TINYINT DEFAULT 0 NOT NULL, code_acces VARCHAR(10) DEFAULT NULL, type_utilisateur VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), UNIQUE INDEX UNIQ_1D1C63B3450FF010 (telephone), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_3D48E0378D0EB82 FOREIGN KEY (candidat_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_3D48E037231F139 FOREIGN KEY (evaluateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_3D48E037265F8806 FOREIGN KEY (parent_dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575611C0C56 FOREIGN KEY (dossier_id) REFERENCES dossier (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575231F139 FOREIGN KEY (evaluateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAB967E626 FOREIGN KEY (receveur_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76611C0C56');
        $this->addSql('ALTER TABLE dossier DROP FOREIGN KEY FK_3D48E0378D0EB82');
        $this->addSql('ALTER TABLE dossier DROP FOREIGN KEY FK_3D48E037231F139');
        $this->addSql('ALTER TABLE dossier DROP FOREIGN KEY FK_3D48E037265F8806');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575611C0C56');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575231F139');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAB967E626');
        $this->addSql('DROP TABLE app_config');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE dossier');
        $this->addSql('DROP TABLE evaluation');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE utilisateur');
    }
}
