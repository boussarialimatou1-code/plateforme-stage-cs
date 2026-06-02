<?php

namespace App\Service;

use App\Entity\Candidat;
use App\Entity\Document;
use App\Entity\Dossier;
use App\Enum\StatutDossier;
use App\Enum\TypeDocument;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DossierManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileUploader $fileUploader,
        private NotificationService $notificationService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
    ) {}

    /**
     * Crée un nouveau dossier avec son candidat.
     * Utilise les transactions pour garantir l'atomicité.
     */

    public function createDossier(Candidat $candidat, Dossier $dossier, array $files): Dossier
    {
        if ($dossier->getTypeStage() === 'academique' && (!isset($files['recommandation']) || $files['recommandation'] === null)) {
            throw new \InvalidArgumentException("La lettre de recommandation est obligatoire pour un stage académique.");
        }
        $this->validateEntity($candidat);
        $this->validateEntity($dossier);

        // Transaction pure : uniquement la persistance
        $this->entityManager->wrapInTransaction(function () use ($candidat, $dossier, $files) {
            if (!$candidat->getId()) {
                $candidat->setRoles(['ROLE_CANDIDAT']);
                $candidat->setCodeAcces((string) random_int(100000, 999999));
            }
            $this->entityManager->persist($candidat);

            $dossier->setCandidat($candidat);
            $dossier->setStatut(StatutDossier::EN_ATTENTE);
            $dossier->setReference($this->generateReference());
            $dossier->setTitre(\sprintf(
                'Stage %s - %s %s',
                ucfirst($dossier->getTypeStage()),
                $candidat->getNom(),
                $candidat->getPrenom()
            ));
            $this->entityManager->persist($dossier);
            $this->handleFileUploads($dossier, $files);
        });
        // ICI : flush est fait, $dossier->getId() est disponible

        try {
            $this->notificationService->sendSubmissionConfirmation($dossier);
        } catch (\Exception $e) {
            $this->logger->error('Échec envoi email confirmation candidat', [
                'dossier_ref' => $dossier->getReference(),
                'candidat_email' => $candidat->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->notificationService->notifyAdminsOfNewSubmission($dossier);
        } catch (\Exception $e) {
            $this->logger->error('Échec notification évaluateurs', [
                'dossier_ref' => $dossier->getReference(),
                'error' => $e->getMessage(),
            ]);
        }

        return $dossier;
    }
    /**
     * Met à jour un dossier existant.
     */
    public function updateDossier(Dossier $dossier, array $files): void
    {
        $this->validateEntity($dossier->getCandidat());
        $this->validateEntity($dossier);

        $this->entityManager->wrapInTransaction(function () use ($dossier, $files) {
            $candidat = $dossier->getCandidat();

            $dossier->setTitre(\sprintf(
                'Stage %s - %s %s',
                ucfirst($dossier->getTypeStage()),
                $candidat->getNom(),
                $candidat->getPrenom()
            ));

            $this->handleFileUploads($dossier, $files, true);
        });
    }

    /**
     * Valide une entité et lance une exception si invalide.
     */
    private function validateEntity(object $entity): void
    {
        $errors = $this->validator->validate($entity);
        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            $errorMessage = "Validation échouée : " . implode(', ', $messages);
            $this->logger->error($errorMessage, [
                'entity' => \get_class($entity),
                'errors' => $messages,
            ]);
            throw new \InvalidArgumentException($errorMessage);
        }
    }

    private function handleFileUploads(Dossier $dossier, array $files, bool $isUpdate = false): void
    {
        foreach ($files as $type => $file) {
            if ($file instanceof UploadedFile) {
                try {
                    $fileName = $this->fileUploader->upload($file);
                } catch (\Exception $e) {
                    $this->logger->error('Échec upload fichier', [
                        'dossier_ref' => $dossier->getReference(),
                        'type' => $type,
                        'original_name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ]);
                    throw new \InvalidArgumentException("Erreur lors de l'upload du fichier " . $file->getClientOriginalName());
                }

                $documentType = match ($type) {
                    'cv' => TypeDocument::CV,
                    'lm' => TypeDocument::LETTRE_MOTIVATION,
                    'id_card' => TypeDocument::PIECE_IDENTITE,
                    'photo' => TypeDocument::PHOTO_IDENTITE,
                    'recommandation' => TypeDocument::LETTRE_RECOMMANDATION,
                    default => throw new \InvalidArgumentException("Type de document inconnu : {$type}"),
                };

                if ($isUpdate) {
                    $existingDoc = $this->entityManager->getRepository(Document::class)->findOneBy([
                        'dossier' => $dossier,
                        'type' => $documentType
                    ]);
                    if ($existingDoc) {
                        $this->fileUploader->delete($existingDoc->getCheminFichier());
                        $this->entityManager->remove($existingDoc);
                    }
                }

                $document = new Document();
                $document->setNomOriginal($file->getClientOriginalName());
                $document->setCheminFichier($fileName);
                $document->setType($documentType);
                $document->setDossier($dossier);

                $this->entityManager->persist($document);
            }
        }
    }

    /**
     * Crée une demande de renouvellement (nouveau dossier lié).
     */
    public function createRenouvellement(Dossier $originalDossier, int $newDuree, UploadedFile $letter): void
    {
        $this->entityManager->wrapInTransaction(function () use ($originalDossier, $newDuree, $letter) {
            $newDossier = new Dossier();
            $newDossier->setCandidat($originalDossier->getCandidat());
            $newDossier->setTypeStage($originalDossier->getTypeStage());
            $newDossier->setDomaine($originalDossier->getDomaine());
            $newDossier->setDureeMois($newDuree);
            $newDossier->setStatut(StatutDossier::EN_ATTENTE);
            $newDossier->setReference($this->generateReference());
            $newDossier->setIsRenouvellement(true);
            $newDossier->setParentDossier($originalDossier);
            $newDossier->setTitre('Renouvellement ' . $originalDossier->getTitre());

            $this->entityManager->persist($newDossier);

            // Upload de la lettre de renouvellement
            $fileName = $this->fileUploader->upload($letter);
            $document = new Document();
            $document->setNomOriginal($letter->getClientOriginalName());
            $document->setCheminFichier($fileName);
            $document->setType(TypeDocument::LETTRE_RENOUVELLEMENT);
            $document->setDossier($newDossier);

            $this->entityManager->persist($document);

            try {
                $this->notificationService->notifyAdminsOfNewSubmission($newDossier);
            } catch (\Exception $e) {
                $this->logger->error('Échec notification nouveau renouvellement', [
                    'dossier_ref' => $newDossier->getReference(),
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function generateReference(): string
    {
        try {
            return 'CS-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        } catch (\Exception $e) {
            $this->logger->error('Échec génération référence dossier', [
                'error' => $e->getMessage(),
            ]);
            // Fallback avec uniqid en cas d'échec de random_bytes
            return 'CS-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
        }
    }
}
