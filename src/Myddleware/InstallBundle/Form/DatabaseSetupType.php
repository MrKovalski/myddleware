<?php 
namespace Myddleware\InstallBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Myddleware\InstallBundle\Entity\DatabaseParameters;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class DatabaseSetupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder
            ->add('database_host')
            ->add('database_port', IntegerType::class)
            ->add('database_name')
            ->add('database_user')
            ->add('database_password')
            ->add('Test', SubmitType::class)
            ->addEventListener(
                FormEvents::SUBMIT,
                [$this, 'onSubmit']
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DatabaseParameters::class,
        ]);
    }

    public function onSubmit(FormEvent $event)
    {
        // dump($event);


    }
}