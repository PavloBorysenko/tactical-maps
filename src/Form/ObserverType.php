<?php

namespace App\Form;

use App\Entity\Observer;
use App\Entity\Map;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;

class ObserverType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Observer Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter observer name'
                ]
            ])
            ->add('icon', TextType::class, [
                'label' => 'Custom Icon',
                'required' => false,
                'attr' => [
                    'class' => 'form-control observer-icon-url',
                    'readonly' => true,
                    'placeholder' => 'Select an icon or leave empty for default'
                ]
            ])
            ->add('map', EntityType::class, [
                'class' => Map::class,
                'choice_label' => 'title',
                'label' => 'Map',
                'attr' => [
                    'class' => 'form-control'
                ],
                'placeholder' => 'Select a map'
            ])
            ->add('rules', TextareaType::class, [
                'label' => 'Rules (JSON)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 8,
                    'placeholder' => 'Enter rules as JSON format, e.g.: {"view_range": 1000, "max_requests": 100}'
                ],
                'help' => 'Enter rules in JSON format. Leave empty for default rules.'
            ]);

        // Add transformer for rules field to handle JSON conversion
        $builder->get('rules')
            ->addModelTransformer(new CallbackTransformer(
                function ($rulesArray) {
                    // Transform array to JSON string for the form
                    return $rulesArray ? json_encode($rulesArray, JSON_PRETTY_PRINT) : '{}';
                },
                function ($rulesString) {
                    // Transform JSON string back to array
                    $decoded = json_decode($rulesString, true);
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Observer::class,
        ]);
    }
} 