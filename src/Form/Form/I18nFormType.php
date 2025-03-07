<?php

namespace EMS\CoreBundle\Form\Form;

use EMS\CoreBundle\Form\Field\SubmitEmsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * use to filter in index page of i18n.
 *
 * @author im
 */
class I18nFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('identifier', null, ['required' => false, 'label' => 'Key'])
            ->add('filter', SubmitEmsType::class, [
                    'attr' => [
                            'class' => 'btn btn-primary btn-sm',
                    ],
                    'icon' => 'fa fa-columns',
            ]);
    }
}
