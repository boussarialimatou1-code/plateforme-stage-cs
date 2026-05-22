<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploader
{
    public function __construct(
        private string $targetDirectory,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
    ) {
        $this->ensureTargetDirectoryExists();
    }

    /**
     * Vérifie et crée le dossier de destination s'il n'existe pas.
     */
    private function ensureTargetDirectoryExists(): void
    {
        if (!is_dir($this->targetDirectory)) {
            if (!mkdir($this->targetDirectory, 0755, true) && !is_dir($this->targetDirectory)) {
                $this->logger->error('Impossible de créer le dossier de destination', [
                    'directory' => $this->targetDirectory,
                ]);
                throw new \RuntimeException(sprintf('Le dossier "%s" n\'existe pas et n\'a pas pu être créé.', $this->targetDirectory));
            }
            $this->logger->info('Dossier de destination créé', [
                'directory' => $this->targetDirectory,
            ]);
        }

        if (!is_writable($this->targetDirectory)) {
            $this->logger->error('Le dossier de destination n\'est pas accessible en écriture', [
                'directory' => $this->targetDirectory,
            ]);
            throw new \RuntimeException(sprintf('Le dossier "%s" n\'est pas accessible en écriture.', $this->targetDirectory));
        }
    }

    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);

        try {
            $extension = $file->guessExtension();
        } catch (\Throwable $t) {
            $extension = null;
        }

        if (!$extension) {
            $extension = $file->getClientOriginalExtension();
        }

        $fileName = $safeFilename . '-' . uniqid() . '.' . $extension;

        // Vérifier à nouveau que le dossier existe (au cas où il aurait été supprimé entre-temps)
        $this->ensureTargetDirectoryExists();

        try {
            $file->move($this->getTargetDirectory(), $fileName);
            $this->logger->info('Fichier uploadé avec succès', [
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $fileName,
                'directory' => $this->targetDirectory,
            ]);
        } catch (FileException $e) {
            $this->logger->error('Échec du déplacement du fichier uploadé', [
                'original_name' => $file->getClientOriginalName(),
                'target_directory' => $this->targetDirectory,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Impossible d'uploader le fichier : " . $e->getMessage());
        }

        return $fileName;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function delete(string $fileName): void
    {
        $filePath = $this->getTargetDirectory() . '/' . $fileName;
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                $this->logger->warning('Impossible de supprimer le fichier', [
                    'file_path' => $filePath,
                ]);
            } else {
                $this->logger->info('Fichier supprimé avec succès', [
                    'file_path' => $filePath,
                ]);
            }
        } else {
            $this->logger->notice('Tentative de suppression d\'un fichier inexistant', [
                'file_path' => $filePath,
            ]);
        }
    }
}