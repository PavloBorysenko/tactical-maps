<?php

namespace App\Form;

use App\Entity\GeoObject;
use App\Entity\Map;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class GeoObjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];
        
        // Hidden field for ID (only for editing)
        if ($isEdit) {
            $builder->add('id', HiddenType::class);
        }
        
        // Hidden field for MapId
        $builder->add('mapId', HiddenType::class, [
            'mapped' => false,
            'attr' => [
                'class' => 'geo-object-map-id'
            ]
        ]);
        
        // Main fields
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a title'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control geo-object-title',
                    'placeholder' => 'Enter title'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control geo-object-description',
                    'placeholder' => 'Enter description (optional)',
                    'rows' => 3
                ]
            ])
            ->add('ttl', ChoiceType::class, [
                'label' => 'Time To Live',
                'required' => true,
                'choices' => [
                    '30 seconds' => 30,
                    '1 minute' => 60,
                    '2 minutes' => 120,
                    '3 minutes' => 180,
                    '5 minutes' => 300,
                    '10 minutes' => 600,
                    '15 minutes' => 900,
                    '20 minutes' => 1200,
                    '30 minutes' => 1800,
                    '1 hour' => 3600,
                    '1 hour 20 minutes' => 4800,
                    '1 hour 30 minutes' => 5400,
                    '2 hours' => 7200,
                    '3 hours' => 10800,
                    '4 hours' => 14400,
                    'Unlimited' => 0,
                ],
                'attr' => [
                    'class' => 'form-control geo-object-ttl'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'required' => true,
                'choices' => [
                    'Point' => 'point',
                    'Polygon' => 'polygon',
                    'Circle' => 'circle',
                    'Line' => 'line'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please select a type'
                    ])
                ],
                'attr' => [
                    'class' => 'form-control geo-object-type',
                    'data-toggle' => 'geo-type-select'
                ]
            ])
            ->add('geoJson', HiddenType::class, [
                'attr' => [
                    'class' => 'geo-object-geojson'
                ]
            ])
            ->add('hash', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'geo-object-hash'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GeoObject::class,
            'is_edit' => false,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'geo_object_form',
        ]);
    }
    
    public function getBlockPrefix(): string
    {
        return 'geo_object';
    }
} 