<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\TypeDocument; // Import indispensable

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomOriginal = null;

    #[ORM\Column(length: 255)]
    private ?string $cheminFichier = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateAjout = null;

    // Mise à jour du type ici avec enumType
    #[ORM\Column(type: 'string', enumType: TypeDocument::class)]
    private ?TypeDocument $type = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossier = null;

    public function __construct()
    {
        $this->dateAjout = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomOriginal(): ?string
    {
        return $this->nomOriginal;
    }

    public function setNomOriginal(string $nomOriginal): static
    {
        $this->nomOriginal = $nomOriginal;
        return $this;
    }

    public function getCheminFichier(): ?string
    {
        return $this->cheminFichier;
    }

    public function setCheminFichier(string $cheminFichier): static
    {
        $this->cheminFichier = $cheminFichier;
        return $this;
    }

    public function getDateAjout(): ?\DateTimeImmutable
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTimeImmutable $dateAjout): static
    {
        $this->dateAjout = $dateAjout;
        return $this;
    }

    // Le getter retourne maintenant l'Enum
    public function getType(): ?TypeDocument
    {
        return $this->type;
    }

    // Le setter attend maintenant l'Enum
    public function setType(?TypeDocument $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDossier(): ?Dossier
    {
        return $this->dossier;
    }

    public function setDossier(?Dossier $dossier): static
    {
        $this->dossier = $dossier;
        return $this;
    }
}