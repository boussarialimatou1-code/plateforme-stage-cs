<?php

namespace App\Entity;

use App\Repository\EvaluationRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\EvaluationAvis;

#[ORM\Entity(repositoryClass: EvaluationRepository::class)]
class Evaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dossier $dossier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $evaluateur = null;

    #[ORM\Column(type: 'string', enumType: EvaluationAvis::class)]
    private ?EvaluationAvis $avis = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateEvaluation = null;

    public function __construct()
    {
        $this->dateEvaluation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEvaluateur(): ?Utilisateur
    {
        return $this->evaluateur;
    }

    public function setEvaluateur(?Utilisateur $evaluateur): static
    {
        $this->evaluateur = $evaluateur;
        return $this;
    }

    public function getAvis(): ?EvaluationAvis
    {
        return $this->avis;
    }

    public function setAvis(?EvaluationAvis $avis): static
    {
        $this->avis = $avis;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getDateEvaluation(): ?\DateTimeImmutable
    {
        return $this->dateEvaluation;
    }

    public function setDateEvaluation(\DateTimeImmutable $dateEvaluation): static
    {
        $this->dateEvaluation = $dateEvaluation;
        return $this;
    }
}