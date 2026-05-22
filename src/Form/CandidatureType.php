<?php

namespace App\Form;

use App\Enum\TypeDomaine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class CandidatureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Regex(
                        pattern: '/^[\p{L}\s\-]+$/u',
                        message: 'Le nom ne doit contenir que des lettres.'
                    )
                ],
            ])
            ->add('prenom', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le prenom est obligatoire.'),
                    new Regex(
                        pattern: '/^[\p{L}\s\-]+$/u',
                        message: 'Le prenom ne doit contenir que des lettres.'
                    )
                ],
            ])
            ->add('email', RepeatedType::class, [
                'type' => EmailType::class,
                'invalid_message' => 'Les adresses email ne correspondent pas.',
                'options' => ['attr' => ['class' => 'form-control']],
                'required' => true,
                'first_options'  => [
                    'label' => 'Adresse Email',
                    'attr' => ['placeholder' => 'Ex: john.doe@mail.com', 'class' => 'form-control'],
                    'constraints' => [
                        new NotBlank(message: 'L\'adresse email est obligatoire.'),
                        new Email(message: 'L\'adresse email n\'est pas valide.'),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmez votre Adresse Email',
                    'attr' => ['placeholder' => 'Repetez l\'adresse email', 'class' => 'form-control']
                ],
                'disabled' => $options['is_edit'],
            ])
            // =================================================================
            // CHAMP TELEPHONE
            // =================================================================
             ->add('telephone', TextType::class, [
                    'constraints' => [
                    new NotBlank(message: 'Le numero de telephone est obligatoire.'),
                     new Regex(
                            pattern: '/^[0-9]{10}$/',
                            message: 'Le numero doit avoir exactement 10 chiffres.'
                     ),
                    ],
                    'attr' => ['placeholder' => '01XXXXXXXX']
                ])
            // =================================================================
            ->add('sexe', ChoiceType::class, [
                'choices' => [
                    'Masculin' => 'M',
                    'Feminin' => 'F',
                ],
                'placeholder' => 'Choisir...',
                'constraints' => [new NotBlank(message: 'Le sexe est obligatoire.')],
            ])
            ->add('dob', DateType::class, [
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(message: 'La date de naissance est obligatoire.'),
                ],
            ])
            ->add('etablissement', TextType::class, [
                'constraints' => [new NotBlank(message: 'L\'etablissement est obligatoire.')],
                'attr' => ['placeholder' => "Ex: Universite d'Abomey-Calavi"]
            ])
            ->add('niveau', ChoiceType::class, [
                'choices' => [
                    'Licence 1' => 'Licence 1',
                    'Licence 2' => 'Licence 2',
                    'Licence 3' => 'Licence 3',
                    'Master 1' => 'Master 1',
                    'Master 2' => 'Master 2',
                    'Doctorat' => 'Doctorat',
                ],
                'placeholder' => 'Choisir...',
                'constraints' => [new NotBlank(message: 'Le niveau d\'etudes est obligatoire.')],
            ])
            ->add('filiere', TextType::class, [
                'constraints' => [new NotBlank(message: 'La filiere est obligatoire.')],
                'attr' => ['placeholder' => 'Ex: Droit Public']
            ])
            ->add('type_stage', ChoiceType::class, [
                'choices' => [
                    'Stage Academique' => 'academique',
                    'Stage Professionnel' => 'professionnel',
                ],
                'placeholder' => 'Choisir...',
                'constraints' => [new NotBlank(message: 'Le type de stage est obligatoire.')],
            ])
            ->add('domaine', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn($domaine) => $domaine->getLabel(), TypeDomaine::cases()),
                    array_map(fn($domaine) => $domaine->value, TypeDomaine::cases())
                ),                 
                'placeholder' => 'Choisir...',
                'constraints' => [new NotBlank(message: 'Le domaine d\'affectation est obligatoire.')],
            ])
            ->add('duree', ChoiceType::class, [
                'choices' => [
                    '1 mois' => 1,
                    '2 mois' => 2,
                    '3 mois' => 3,
                    '6 mois' => 6,
                ],
                'placeholder' => 'Choisir...',
                'constraints' => [new NotBlank(message: 'La duree du stage est obligatoire.')],
            ])
            ->add('cv', FileType::class, [
                'required' => !$options['is_edit'],
                'mapped' => false,
                'constraints' => array_merge(
                    $options['is_edit'] ? [] : [new NotBlank(message: 'Veuillez uploader votre CV.')],
                    [new File(
                        maxSize: '2M',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Le CV doit etre au format PDF uniquement.',
                        maxSizeMessage: 'Le fichier est trop lourd. La taille maximale autorisee est de 2 Mo.'
                    )]
                ),
            ])
            ->add('lm', FileType::class, [
                'required' => !$options['is_edit'],
                'mapped' => false,
                'constraints' => array_merge(
                    $options['is_edit'] ? [] : [new NotBlank(message: 'Veuillez uploader votre lettre de motivation.')],
                    [new File(
                        maxSize: '2M',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'La lettre de motivation doit etre au format PDF uniquement.',
                        maxSizeMessage: 'Le fichier est trop lourd. La taille maximale autorisee est de 2 Mo.'
                    )]
                ),
            ])
            ->add('id_card', FileType::class, [
                'required' => !$options['is_edit'],
                'mapped' => false,
                'constraints' => array_merge(
                    $options['is_edit'] ? [] : [new NotBlank(message: 'Veuillez uploader votre piece d\'identite.')],
                    [new File(
                        maxSize: '2M',
                        mimeTypes: ['application/pdf', 'image/png', 'image/jpeg'],
                        mimeTypesMessage: 'La piece d\'identite doit etre au format PDF, PNG ou JPG.',
                        maxSizeMessage: 'Le fichier est trop lourd. La taille maximale autorisee est de 2 Mo.'
                    )]
                ),
            ])
            ->add('photo', FileType::class, [
                'required' => !$options['is_edit'],
                'mapped' => false,
                'constraints' => array_merge(
                    $options['is_edit'] ? [] : [new NotBlank(message: 'Veuillez uploader votre photo d\'identite.')],
                    [new File(
                        maxSize: '2M',
                        mimeTypes: ['image/png', 'image/jpeg'],
                        mimeTypesMessage: 'La photo doit etre au format PNG ou JPG.',
                        maxSizeMessage: 'La photo est trop lourde. La taille maximale autorisee est de 2 Mo.'
                    )]
                ),
            ])
            ->add('recommandation', FileType::class, [
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'La recommandation doit etre au format PDF uniquement.',
                        maxSizeMessage: 'Le fichier est trop lourd. La taille maximale autorisee est de 2 Mo.'
                    ),
                    new Callback(function($object, ExecutionContextInterface $context) {
                        $form = $context->getRoot();
                        if (!$form->has('type_stage')) return;
                        $typeStage = $form->get('type_stage')->getData();
                        if ($typeStage === 'academique' && empty($object) && !$form->getConfig()->getOption('is_edit')) {
                            $context->buildViolation('La lettre de recommandation est obligatoire pour un stage academique.')
                                ->atPath('recommandation')
                                ->addViolation();
                        }
                    })
                ],
            ])
            ->add('fax_number', TextType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => ['style' => 'display:none', 'tabindex' => '-1', 'autocomplete' => 'off']
            ])
        ;

        // =================================================================
        // PRE_SUBMIT LISTENER - Correction nettoyage téléphone
        // =================================================================
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();

            if (isset($data['telephone'])) {
                // Nettoyer le téléphone en gardant uniquement les chiffres
                $data['telephone'] = preg_replace('/[^0-9]/', '', $data['telephone']);
            }

            if (isset($data['email']['first'])) { 
                $data['email']['first'] = trim($data['email']['first']); 
            }
            if (isset($data['email']['second'])) { 
                $data['email']['second'] = trim($data['email']['second']); 
            }

            $event->setData($data);
        });
        // =================================================================
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'is_edit' => false,
        ]);
    }
}