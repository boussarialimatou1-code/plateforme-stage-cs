<?php

/**
 * ================================================================================================
 * FORMULAIRE : TYPE D'ÉVALUATION (EvaluationType)
 * ================================================================================================
 *
 * FICHIER : src/Form/EvaluationType.php
 *
 * ROLE PRINCIPAL :
 * Ce fichier définit la structure du formulaire d'évaluation d'un dossier.
 * Il est utilisé par les évaluateurs et admins pour donner leur avis sur un dossier.
 *
 * --------------------------------------------------------------------------------
 * CHAMPS DU FORMULAIRE :
 * --------------------------------------------------------------------------------
 *
 * 1. avis (ChoiceType - Radio buttons)
 *    → Les 3 choix : FAVORABLE, RESERVE, DEFAVORABLE
 *    → Affiché sous forme de boutons radio
 *    → Obligatoire (NotBlank constraint)
 *
 * 2. commentaire (TextareaType)
 *    → Zone de texte pour l'appréciation
 *    → Optionnel (required => false)
 *    → 4 lignes de hauteur
 *
 * --------------------------------------------------------------------------------
 * UTILISATION DANS UN CONTRÔLEUR :
 * --------------------------------------------------------------------------------
 *
 * // Dans EvaluatorController::show()
 * $evaluation = new Evaluation();
 * $form = $this->createForm(EvaluationType::class, $evaluation);
 *
 * // Dans le template Twig
 * {{ form_start(form) }}
 *     {{ form_row(form.avis) }}
 *     {{ form_row(form.commentaire) }}
 *     <button type="submit">Valider</button>
 * {{ form_end(form) }}
 *
 * --------------------------------------------------------------------------------
 * DEPENDANCES :
 * --------------------------------------------------------------------------------
 */

namespace App\Form;

// Importe l'entité Evaluation (la classe liée au formulaire)
use App\Entity\Evaluation;

// Importe l'énumération des avis possibles
use App\Enum\EvaluationAvis;

// Importe les types de champs Symfony
use App\Enum\TypeStructure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
// Importe les classes pour construire le formulaire
use Symfony\Component\Form\FormBuilderInterface;

// Importe la classe pour configurer les options
use Symfony\Component\OptionsResolver\OptionsResolver;

// Importe les contraintes de validation
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * ================================================================================================
 * CLASSE EvaluationType
 * ================================================================================================
 *
 * Définit le formulaire d'évaluation.
 *
 * HÉRITAGE :
 * Étend AbstractType, qui est la classe de base pour tous les types de formulaire Symfony.
 *
 * MÉTHODES REQUISES :
 * - buildForm() → Définit les champs du formulaire
 * - configureOptions() → Définit les options (data_class, etc.)
 */
class EvaluationType extends AbstractType
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
     *    → Méthode principale : add()
     *
     * 2. array $options
     *    → Les options passées au formulaire
     *    → Peut contenir : action, method, data_class, etc.
     *
     * QUE FAIT CETTE MÉTHODE ?
     * Elle ajoute 2 champs au formulaire :
     * 1. 'avis' → Choix multiple (radio buttons)
     * 2. 'commentaire' → Zone de texte
     *
     * @param FormBuilderInterface $builder Le constructeur de formulaire
     * @param array $options Les options du formulaire
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // On récupère la liste des avis possibles
        $choices = EvaluationAvis::cases();

        // Si l'option can_reserve est à false, on retire l'avis "RESERVE" des choix
        if (!$options['can_reserve']) {
            $choices = array_filter($choices, fn($avis) => $avis !== EvaluationAvis::RESERVE);
        }

        // --------------------------------------------------------------------------------
        // CHAMP 1 : avis (ChoiceType - Radio buttons)
        // --------------------------------------------------------------------------------

        // Ce champ permet à l'évaluateur de choisir son avis sur le dossier
        $builder
            ->add('avis', ChoiceType::class, [
                // Le label affiché au-dessus du champ
                'label' => 'Avis de l\'expert',

                // Les choix possibles
                'choices' => $choices,

                // Quelle propriété de l'enum utiliser comme valeur soumise ?
                // ->value retourne la valeur string ('favorable', 'reserve', 'defavorable')
                // fn (?EvaluationAvis $avis) est une fonction fléchée (arrow function)
                'choice_value' => fn(?EvaluationAvis $avis) => $avis?->value,

                // Quel texte afficher pour chaque choix ?
                // ->getLabel() retourne le label lisible
                // Ex: 'favorable' → 'Favorable (Admission directe)'
                'choice_label' => fn(EvaluationAvis $avis) => $avis->getLabel(),

                // COMMENT AFFICHER LES CHOIX ?
                // expanded => true → Affiche en radio buttons (pas en select dropdown)
                // multiple => false → Un seul choix possible (pas de checkboxes)
                'expanded' => true,
                'multiple' => false,

                // Classe CSS personnalisée pour le style
                // Permet de cibler ce champ dans le CSS
                'attr' => ['class' => 'avis-radio-group'],

                // CONTRAINTES DE VALIDATION
                // Ce champ est OBLIGATOIRE
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner un avis'),
                ],
            ])

            // --------------------------------------------------------------------------------
            // CHAMP 2 : commentaire (TextareaType)
            // --------------------------------------------------------------------------------

            // Ce champ permet à l'évaluateur d'ajouter un commentaire libre
            ->add('commentaire', TextareaType::class, [
                // Le label affiché
                'label' => 'Commentaire / Appréciation',

                // Est-ce que ce champ est obligatoire ?
                // NON → required => false
                // L'évaluateur peut soumettre sans commentaire
                'required' => false,

                // ATTRIBUTS HTML PERSONNALISÉS
                'attr' => [
                    // Classe CSS pour le style Bootstrap
                    'class' => 'form-control',

                    // Nombre de lignes visibles
                    'rows' => 4,

                    // Texte d'indice (placeholder)
                    'placeholder' => 'Ajoutez vos remarques sur le dossier...'
                ],
            ])

            // --------------------------------------------------------------------------------
            // CHAMPS DE DATES (Non mappés à l'entité Evaluation mais utilisés pour Dossier)
            // --------------------------------------------------------------------------------
            ->add(
                'dateDebutStage',
                DateType::class,
                [
                    'label' => 'Date de début du stage',
                    'widget' => 'single_text',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control']
                ]
            )->add('structure', EnumType::class, [
                    'label' => 'Structure d\'affection',
                    'required' => true,
                    'mapped' => false,
                    'class' => TypeStructure::class,
                    'attr' => ['class' => 'form-control']
                ])
        ;

        // NOTE : Le point-virgule final est après le dernier add()
        // C'est une convention Symfony pour les builders
    }

    /**
     * ================================================================================================
     * METHODE : configureOptions()
     * ================================================================================================
     *
     * ROLE : Configure les options par défaut du formulaire
     *
     * PARAMÈTRE :
     *
     * OptionsResolver $resolver
     *    → L'objet qui permet de définir les options
     *    → Méthode principale : setDefaults()
     *
     * QUELLE EST L'OPTION LA PLUS IMPORTANTE ?
     *
     * data_class => Evaluation::class
     *
     * Cela signifie que ce formulaire est "lié" à l'entité Evaluation.
     *
     * CONSÉQUENCES :
     * 1. Les données du formulaire sont automatiquement mappées vers un objet Evaluation
     * 2. Quand on soumet, Symfony appelle les setters de Evaluation
     * 3. La validation utilise les contraintes de l'entité Evaluation
     *
     * EXEMPLE DE MAPPING :
     *
     * Formulaire → Entité
     * avis        → setAvis()
     * commentaire → setCommentaire()
     *
     * @param OptionsResolver $resolver Le résolveur d'options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        // Définit les options par défaut du formulaire
        $resolver->setDefaults([
            // L'entité liée à ce formulaire
            // Tous les champs du formulaire correspondent aux propriétés de cette classe
            'data_class' => Evaluation::class,
            // Option pour autoriser ou non la mise en réserve
            'can_reserve' => true,
        ]);
    }
}
