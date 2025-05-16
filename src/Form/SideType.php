<?php

namespace App\Form;

use App\Entity\Side;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Side Name',
                'attr' => [
                    'placeholder' => 'Enter side name (e.g., "Team Alpha")'
                ]
            ])
            ->add('color', ColorType::class, [
                'label' => 'Side Color',
                'help' => 'This color will be used to display objects belonging to this side'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Optional description of this side'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Side::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'side',          
        ]);
    }
} 