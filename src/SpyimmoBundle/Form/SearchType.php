<?php

namespace SpyimmoBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('minBudget')
            ->add('maxBudget')
            ->add('minSurface')
            ->add('maxSurface')
            ->add('minBedroom')
            ->add('minRoom')
            ->add('maxRoom')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'SpyimmoBundle\Entity\Search',
        ));
    }
}
