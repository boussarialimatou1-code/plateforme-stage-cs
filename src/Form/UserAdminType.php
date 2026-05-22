<?php

/**
 * ================================================================================================
 * FORMULAIRE : TYPE UTILISATEUR ADMIN (UserAdminType)
 * ================================================================================================
 * 
 * FICHIER : src/Form/UserAdminType.php
 * 
 * ROLE PRINCIPAL :
 * Ce fichier définit la structure du formulaire de création d'un compte
 * administrateur ou évaluateur.
 * 
 * UTILISATION :
 * Utilisé exclusivement par les admins pour créer de nouveaux comptes
 * pour leur équipe (autres admins ou évaluateurs).
 * 
 * --------------------------------------------------------------------------------
 * CHAMPS DU FORMULAIRE :
 * --------------------------------------------------------------------------------
 * 
 * 1. username (TextType)
 *    → Nom d'utilisateur (identifiant de connexion)
 *    → Obligatoire
 *    → Unique en BDD
 * 
 * 2. email (EmailType)
 *    → Adresse email professionnelle
 *    → Obligatoire
 *    → Validation automatique du format email
 * 
 * 3. nom (TextType)
 *    → Nom de famille
 *    → Affiché en MAJUSCULES (style: text-transform: uppercase)
 *    → Obligatoire
 * 
 * 4. prenom (TextType)
 *    → Prénom
 *    → Obligatoire
 * 
 * 5. roles (ChoiceType - Select multiple)
 *    → Rôle(s) de l'agent
 *    → Choix multiples possibles : ROLE_EVALUATEUR, ROLE_ADMIN
 *    → Affiché en dropdown (pas en checkboxes)
 *    → Utilise Select2 pour un meilleur UX
 * 
 * 6. plainPassword (PasswordType)
 *    → Mot de passe temporaire
 *    → NON mappé à l'entité (mapped => false)
 *    → Hashé manuellement dans le contrôleur
 *    → Obligatoire, min 8 caractères
 * 
 * --------------------------------------------------------------------------------
 * POURQUOI plainPassword N'EST PAS MAPPÉ ?
 * --------------------------------------------------------------------------------
 * 
 * L'entité User a une propriété $password qui stocke le mot de passe HASHÉ.
 * 
 * Dans le formulaire, on veut :
 * 1. Faire saisir un mot de passe en clair à l'admin
 * 2. Hacher ce mot de passe avec UserPasswordHasher
 * 3. Stocker le hash dans $password
 * 
 * Si on mappait directement :
 * - Le mot de passe en clair serait stocké dans $password ❌
 * - Ce serait une faille de sécurité majeure
 * 
 * Solution :
 * - plainPassword => Champ temporaire, non mappé
 * - Dans le contrôleur : hashPassword(plainPassword) → $password
 * 
 * --------------------------------------------------------------------------------
 * UTILISATION DANS UN CONTRÔLEUR :
 * --------------------------------------------------------------------------------
 * 
 * // Dans AdminUserController::new()
 * $user = new User();
 * $form = $this->createForm(UserAdminType::class, $user);
 * $form->handleRequest($request);
 * 
 * if ($form->isSubmitted() && $form->isValid()) {
 *     $plainPassword = $form->get('plainPassword')->getData();
 *     $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
 *     $user->setPassword($hashedPassword);
 *     // ...
 * }
 * 
 * --------------------------------------------------------------------------------
 * DEPENDANCES :
 * --------------------------------------------------------------------------------
 */

namespace App\Form;

// Importe l'entité Utilisateur (la classe liée au formulaire)
use App\Entity\Utilisateur;

// Importe les types de champs Symfony
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

// Importe les classes pour construire le formulaire
use Symfony\Component\Form\FormBuilderInterface;

// Importe la classe pour configurer les options
use Symfony\Component\OptionsResolver\OptionsResolver;

// Importe les contraintes de validation
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * ================================================================================================
 * CLASSE UserAdminType
 * ================================================================================================
 * 
 * Définit le formulaire de création d'un compte admin/évaluateur.
 * 
 * HÉRITAGE :
 * Étend AbstractType, qui est la classe de base pour tous les types de formulaire Symfony.
 * 
 * MÉTHODES REQUISES :
 * - buildForm() → Définit les champs du formulaire
 * - configureOptions() → Définit les options (data_class, etc.)
 */
class UserAdminType extends AbstractType
{
    /**
     * ================================================================================================
     * METHODE : buildForm()
     * ================================================================================================
     * 
     * ROLE : Construit le formulaire en ajoutant les champs un par un
     * 
     * PARAMÈTRES :
     * 
     * 1. FormBuilderInterface $builder
     *    → L'objet qui permet d'ajouter des champs au formulaire
     * 
     * 2. array $options
     *    → Les options passées au formulaire
     * 
     * QUE FAIT CETTE MÉTHODE ?
     * Elle ajoute 6 champs au formulaire :
     * 1. username → Nom d'utilisateur
     * 2. email → Email professionnel
     * 3. nom → Nom de famille
     * 4. prenom → Prénom
     * 5. roles → Rôle(s)
     * 6. plainPassword → Mot de passe
     * 
     * @param FormBuilderInterface $builder Le constructeur de formulaire
     * @param array $options Les options du formulaire
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // --------------------------------------------------------------------------------
        // CHAMP 1 : email (EmailType)
        // --------------------------------------------------------------------------------
        
        // L'adresse email professionnelle de l'agent
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse Email professionnel',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'L\'adresse email est obligatoire.'),
                    new \Symfony\Component\Validator\Constraints\Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.'),
                ],
            ])
            
            // --------------------------------------------------------------------------------
            // CHAMP 3 : nom (TextType)
            // --------------------------------------------------------------------------------
            
            // Le nom de famille de l'agent
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                
                // Style CSS personnalisé pour afficher en MAJUSCULES
                // text-transform: uppercase → Force les majuscules visuellement
                // Note : Le vrai passage en majuscules se fait dans le contrôleur
                // avec strtoupper($user->getNom())
                'attr' => ['class' => 'form-control', 'style' => 'text-transform: uppercase;']
            ])
            
            // --------------------------------------------------------------------------------
            // CHAMP 4 : prenom (TextType)
            // --------------------------------------------------------------------------------
            
            // Le prénom de l'agent
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control']
            ])
            
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'attr' => ['class' => 'form-control', 'placeholder' => '01XXXXXXXX'],
                'constraints' => [
                    new NotBlank(message: 'Le numéro de téléphone est obligatoire.'),
                    new \Symfony\Component\Validator\Constraints\Regex(
                        pattern: '/^01(\s*[0-9]){8}\s*$/',
                        message: 'Le numéro de téléphone doit commencer par 01 et comporter 10 chiffres.'
                    ),
                ],
            ])
            
            // --------------------------------------------------------------------------------
            // CHAMP 5 : roles (ChoiceType - Select multiple)
            // --------------------------------------------------------------------------------
            
            // Le(s) rôle(s) de l'agent dans la plateforme
            ->add('roles', ChoiceType::class, [
                // Le label affiché
                'label' => "Rôle de l'agent",
                
                // Les choix possibles
                'choices' => (function() use ($options) {
                    $choices = ['Évaluateur' => 'ROLE_EVALUATEUR'];
                    // Seul l'Admin Principal ou un Admin autorisé peut créer d'autres Admins
                    if ($options['is_main_admin'] || $options['is_authorized_to_manage_admins']) {
                        $choices['Administrateur'] = 'ROLE_ADMIN';
                    }
                    return $choices;
                })(),
                
                // PEUT-ON COCHER PLUSIEURS CASES ?
                // multiple => true → Oui, on peut donner plusieurs rôles
                // expanded => false → Affiché en dropdown (pas en checkboxes)
                'multiple' => true,
                'expanded' => false,
                
                // Classe CSS pour le style
                // 'select2' active le plugin jQuery Select2 pour un dropdown amélioré
                'attr' => ['class' => 'form-control select2'],
            ]);

        // Champ spécial pour déléguer la gestion des admins
        if ($options['is_main_admin']) {
            $builder->add('canManageAdmins', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'Autoriser cet administrateur à gérer les autres comptes administrateurs',
                'required' => false,
                'mapped' => false, // Géré manuellement dans le contrôleur
                'attr' => ['class' => 'custom-control-input'],
                'label_attr' => ['class' => 'custom-control-label'],
                'row_attr' => ['class' => 'custom-control custom-checkbox mb-3'],
            ]);
        }

        // Champ pour désigner l'évaluateur principal (uniquement par l'Admin Principal)
        if ($options['is_main_admin']) {
            $builder->add('isMainEvaluator', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'Désigner comme Évaluateur Principal (Peut répartir les dossiers)',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'custom-control-input'],
                'label_attr' => ['class' => 'custom-control-label'],
                'row_attr' => ['class' => 'custom-control custom-checkbox mb-3'],
            ]);
        }
    }

    /**
     * ================================================================================================
     * METHODE : configureOptions()
     * ================================================================================================
     * 
     * ROLE : Configure les options par défaut du formulaire
     * 
     * OPTION PRINCIPALE :
     * 
     * data_class => User::class
     * 
     * Cela lie le formulaire à l'entité User.
     * 
     * CONSÉQUENCES :
     * 1. Les champs username, email, nom, prenom, roles sont automatiquement mappés
     * 2. plainPassword n'est PAS mappé (mapped => false)
     * 3. La validation utilise les contraintes de l'entité User
     * 
     * @param OptionsResolver $resolver Le résolveur d'options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        // Définit les options par défaut du formulaire
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
            'is_main_admin' => false,
            'is_edit' => false,
            'is_authorized_to_manage_admins' => false,
        ]);
    }
}
