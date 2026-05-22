<?php

namespace App\Entity;

use App\Enum\TypeStructure;
use App\Repository\DossierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\StatutDossier;

#[ORM\Entity(repositoryClass: DossierRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Dossier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(length: 50)]
    private ?string $typeStage = null;

    #[ORM\Column(length: 255)]
    private ?string $domaine = null;

    #[ORM\Column]
    private ?int $dureeMois = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateDebutStage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateFinStage = null;

    #[ORM\Column(length: 50)]
    private ?StatutDossier $statut = null;

    #[ORM\ManyToOne(inversedBy: 'dossiers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $candidat = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'dossier', orphanRemoval: true)]
    private Collection $documents;

    /**
     * @var Collection<int, Evaluation>
     */
    #[ORM\OneToMany(targetEntity: Evaluation::class, mappedBy: 'dossier')]
    private Collection $evaluations;

    #[ORM\ManyToOne(targetEntity: Evaluateur::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Evaluateur $evaluateur = null;

    // ✅ AJOUT : Flag pour indiquer que la lettre officielle est finalisée
    #[ORM\Column(options: ["default" => false])]
    private bool $lettreFinalisee = false;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $parentDossier = null;

    #[ORM\Column(options: ["default" => false])]
    private bool $isRenouvellement = false;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->evaluations = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getTypeStage(): ?string
    {
        return $this->typeStage;
    }

    public function setTypeStage(string $typeStage): static
    {
        $this->typeStage = $typeStage;
        return $this;
    }

    public function getDomaine(): ?string
    {
        return $this->domaine;
    }

    public function setDomaine(string $domaine): static
    {
        $this->domaine = $domaine;
        return $this;
    }

    public function getDureeMois(): ?int
    {
        return $this->dureeMois;
    }

    public function setDureeMois(?int $dureeMois): static
    {
        $this->dureeMois = $dureeMois;
        return $this;
    }

    public function getDateDebutStage(): ?\DateTimeImmutable
    {
        return $this->dateDebutStage;
    }

    public function setDateDebutStage(?\DateTimeImmutable $dateDebutStage): static
    {
        $this->dateDebutStage = $dateDebutStage;
        return $this;
    }

    public function getDateFinStage(): ?\DateTimeImmutable
    {
        return $this->dateFinStage;
    }

    public function setDateFinStage(?\DateTimeImmutable $dateFinStage): static
    {
        $this->dateFinStage = $dateFinStage;
        return $this;
    }

    public function getJoursRestants(): ?int
    {
        if (!$this->dateFinStage || !$this->dateDebutStage) {
            return null;
        }

        $now = new \DateTimeImmutable();

        if ($now < $this->dateDebutStage) {
            return $this->dateDebutStage->diff($this->dateFinStage)->days;
        }

        if ($now > $this->dateFinStage) {
            return 0;
        }

        return $now->diff($this->dateFinStage)->days;
    }

    public function getStatut(): ?StatutDossier
    {
        return $this->statut;
    }

    public function setStatut(StatutDossier $statut): static
    {
        if ($this->statut !== null && in_array($this->statut, [StatutDossier::VALIDE, StatutDossier::REJETE])) {
            return $this;
        }
        $this->statut = $statut;
        return $this;
    }

    #[ORM\PrePersist]
    public function setInitialData(): void
    {
        if ($this->dateCreation === null) {
            $this->dateCreation = new \DateTimeImmutable();
        }
    }

    public function getCandidat(): ?Utilisateur
    {
        return $this->candidat;
    }

    public function setCandidat(?Utilisateur $candidat): static
    {
        $this->candidat = $candidat;
        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setDossier($this);
        }
        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getDossier() === $this) {
                $document->setDossier(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Evaluation>
     */
    public function getEvaluations(): Collection
    {
        return $this->evaluations;
    }

    public function addEvaluation(Evaluation $evaluation): static
    {
        if (!$this->evaluations->contains($evaluation)) {
            $this->evaluations->add($evaluation);
            $evaluation->setDossier($this);
        }
        return $this;
    }

    public function removeEvaluation(Evaluation $evaluation): static
    {
        if ($this->evaluations->removeElement($evaluation)) {
            if ($evaluation->getDossier() === $this) {
                $evaluation->setDossier(null);
            }
        }
        return $this;
    }

    public function getDerniereEvaluation(): ?Evaluation
    {
        if ($this->evaluations->isEmpty()) {
            return null;
        }

        $iterator = $this->evaluations->getIterator();
        $iterator->uasort(function ($a, $b) {
            return $b->getId() <=> $a->getId();
        });

        return iterator_to_array($iterator)[0] ?? null;
    }

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroOfficiel = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $signatureOfficielle = null;

    #[ORM\Column(nullable: true, enumType: TypeStructure::class)]
    private ?TypeStructure $structure = null;

    public function getNumeroOfficiel(): ?string
    {
        return $this->numeroOfficiel;
    }

    public function setNumeroOfficiel(?string $numeroOfficiel): self
    {
        $this->numeroOfficiel = $numeroOfficiel;
        return $this;
    }

    public function getSignatureOfficielle(): ?string
    {
        return $this->signatureOfficielle;
    }

    public function setSignatureOfficielle(?string $signatureOfficielle): self
    {
        $this->signatureOfficielle = $signatureOfficielle;
        return $this;
    }

    public function getEvaluateur(): ?Evaluateur
    {
        return $this->evaluateur;
    }

    public function setEvaluateur(?Evaluateur $evaluateur): self
    {
        $this->evaluateur = $evaluateur;
        return $this;
    }

    // ✅ AJOUT : Getter/Setter pour le flag lettreFinalisee
    public function isLettreFinalisee(): bool
    {
        return $this->lettreFinalisee;
    }

    public function setLettreFinalisee(bool $lettreFinalisee): self
    {
        $this->lettreFinalisee = $lettreFinalisee;
        return $this;
    }

    public function getParentDossier(): ?self
    {
        return $this->parentDossier;
    }

    public function setParentDossier(?self $parentDossier): self
    {
        $this->parentDossier = $parentDossier;
        return $this;
    }

    public function isRenouvellement(): bool
    {
        return $this->isRenouvellement;
    }

    public function setIsRenouvellement(bool $isRenouvellement): self
    {
        $this->isRenouvellement = $isRenouvellement;
        return $this;
    }

    public function getStructure(): ?TypeStructure
    {
        return $this->structure;
    }

    public function setStructure(?TypeStructure $structure): static
    {
        $this->structure = $structure;

        return $this;
    }
}
