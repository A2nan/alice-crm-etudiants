<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'nouveau mot de passe',
                    ],
                ],
                'first_options' => [
                    'constraints' => [
                        // au moins 12 caracteres, pas de plafond bas (OWASP ASVS 2.1.1/2.1.2)
                        new Length([
                            'min' => 12,
                            'max' => 4096,
                            'minMessage' => 'Le mot de passe doit contenir au moins 12 caractères',
                            'maxMessage' => 'Le mot de passe est trop long' 
                        ]),
                        // invalide si null
                        new NotBlank([
                            'message' => 'Veuillez renseigner un mot de passe !'
                        ]),
                        // 1 maj + 1 min + 1 chiffre + 1 caractere special, 12 caracteres minimum 
                        new Regex([
                            'pattern' => '/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{12,}$/',
                            'message' => 'Votre mot de passe doit contenir 1 majuscule, 1 minuscule, 1 caractère spécial, 1 chiffre et doit contenir au moins 12 caractères'
                        ]),
                        // checked against the haveibeenpwned list (k-anonymity: the password never leaves the server in clear)
                        new NotCompromisedPassword([
                            'message' => 'Ce mot de passe est apparu dans une fuite de données. Merci d’en choisir un autre.'
                        ]),
                    ],
                    'label' => 'Nouveau mot de passe',
                ],
                'second_options' => [
                    'label' => 'Confirmer votre mot de passe',
                ],
                'invalid_message' => 'Votre mot de passe et la confirmation doivent être identiques.',
                // Instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Réinitialisez votre mot de passe',
                'attr' => [
                    'class' => 'btn-alice-form'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
