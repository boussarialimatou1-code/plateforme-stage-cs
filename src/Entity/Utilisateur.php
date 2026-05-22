<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
#[UniqueEntity(fields: ['telephone'], message: 'Ce numéro de téléphone est déjà utilisé.')]
// --- CONFIGURATION DE L'HÉRITAGE ---
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type_utilisateur', type: 'string')]
#[ORM\DiscriminatorMap([
    'candidat' => Candidat::class,
    'evaluateur' => Evaluateur::class,
    'admin' => Admin::class
])]
// -----------------------------------
abstract class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\-]+$/u',
        message: 'Le nom ne doit contenir que des lettres.'
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^[\p{L}\s\-]+$/u',
        message: 'Le prénom ne doit contenir que des lettres.'
    )]
    private ?string $prenom = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'adresse email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.')]
    private ?string $email = null;

    

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 20, unique: true)]
    #[Assert\NotBlank(message: 'Le numero de telephone est obligatoire.')]
    private ?string $telephone = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Choice(choices: ['M', 'F'], message: 'Le sexe doit être M ou F.')]
    private ?string $sexe = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: 'La date de naissance est obligatoire.')]
    #[Assert\LessThan('-16 years', message: 'Vous devez avoir au moins 16 ans pour postuler.')]
    #[Assert\GreaterThan('-90 years', message: 'La date de naissance n\'est pas réaliste.')]
    private ?\DateTimeImmutable $dob = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'L\'établissement est obligatoire.')]
    private ?string $etablissement = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Le niveau d\'études est obligatoire.')]
    private ?string $niveau = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'La filière est obligatoire.')]
    private ?string $filiere = null;

    #[ORM\Column(options: ["default" => false])]
    private bool $doitChangerMotDePasse = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\Column(options: ["default" => false])]
    private bool $canManageAdmins = false;

    #[ORM\Column(options: ["default" => false])]
    private bool $isMainEvaluator = false;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'receveur', orphanRemoval: true)]
    private Collection $notifications;

    /**
     * @var Collection<int, Dossier>
     */
    #[ORM\OneToMany(targetEntity: Dossier::class, mappedBy: 'candidat', orphanRemoval: true)]
    private Collection $dossiers;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->dossiers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = mb_strtoupper(trim($nom), 'UTF-8');
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = mb_convert_case(trim($prenom), MB_CASE_TITLE, 'UTF-8');
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = trim($email);
        return $this;
    }



    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = str_replace(' ', '', trim($telephone));
        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): static
    {
        $this->sexe = $sexe;
        return $this;
    }

    public function getDob(): ?\DateTimeImmutable
    {
        return $this->dob;
    }

    public function setDob(?\DateTimeImmutable $dob): static
    {
        $this->dob = $dob;
        return $this;
    }

    public function getEtablissement(): ?string
    {
        return $this->etablissement;
    }

    public function setEtablissement(?string $etablissement): static
    {
        $this->etablissement = $etablissement;
        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(?string $niveau): static
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getFiliere(): ?string
    {
        return $this->filiere;
    }

    public function setFiliere(?string $filiere): static
    {
        $this->filiere = $filiere;
        return $this;
    }

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeAcces = null;

    public function getCodeAcces(): ?string { return $this->codeAcces; }
    public function setCodeAcces(?string $codeAcces): self { 
        $this->codeAcces = $codeAcces; 
        return $this; 
    }

    public function isDoitChangerMotDePasse(): bool
    {
        return $this->doitChangerMotDePasse;
    }

    public function setDoitChangerMotDePasse(bool $doitChangerMotDePasse): static
    {
        $this->doitChangerMotDePasse = $doitChangerMotDePasse;
        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getNotifications(): Collection { return $this->notifications; }
    public function addNotification(Notification $notification): static { 
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setReceveur($this);
        }
        return $this;
    }
    public function removeNotification(Notification $notification): static {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getReceveur() === $this) { $notification->setReceveur(null); }
        }
        return $this;
    }
    public function getDossiers(): Collection { return $this->dossiers; }
    public function addDossier(Dossier $dossier): static {
        if (!$this->dossiers->contains($dossier)) {
            $this->dossiers->add($dossier);
            $dossier->setCandidat($this);
        }
        return $this;
    }
    public function removeDossier(Dossier $dossier): static {
        if ($this->dossiers->removeElement($dossier)) {
            if ($dossier->getCandidat() === $this) { $dossier->setCandidat(null); }
        }
        return $this;
    }

    public function canManageAdmins(): bool { return $this->canManageAdmins; }
    public function setCanManageAdmins(bool $canManageAdmins): self {
        $this->canManageAdmins = $canManageAdmins;
        return $this;
    }

    public function isMainEvaluator(): bool { return $this->isMainEvaluator; }
    public function setIsMainEvaluator(bool $isMainEvaluator): self {
        $this->isMainEvaluator = $isMainEvaluator;
        return $this;
    }
}