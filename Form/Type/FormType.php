<?php

namespace Harmony\Bundle\AdminBundle\Form\Type;

use ArrayObject;
use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Harmony\Bundle\AdminBundle\Form\EventListener\HarmonyAdminTabSubscriber;
use Harmony\Bundle\AdminBundle\Form\Type\Configurator\TypeConfiguratorInterface;
use Harmony\Bundle\AdminBundle\Form\Util\FormTypeHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Custom form type that deals with some of the logic used to render the
 * forms used to create and edit HarmonyAdmin entities.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class FormType extends AbstractType
{

    /** @var ConfigManager $configManager */
    private $configManager;

    /** @var TypeConfiguratorInterface[] $configurators */
    private $configurators;

    /**
     * @param ConfigManager               $configManager
     * @param TypeConfiguratorInterface[] $configurators
     */
    public function __construct(ConfigManager $configManager, array $configurators = [])
    {
        $this->configManager = $configManager;
        $this->configurators = $configurators;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $model            = $options['model'];
        $view             = $options['view'];
        $modelConfig      = $this->configManager->getModelConfig($model);
        $modelProperties  = $modelConfig[$view]['fields'] ?? [];
        $formTabs         = [];
        $currentFormTab   = null;
        $formGroups       = [];
        $currentFormGroup = null;

        foreach ($modelProperties as $name => $metadata) {
            $formFieldOptions = $metadata['type_options'];

            // Configure options using the list of registered type configurators:
            foreach ($this->configurators as $configurator) {
                if ($configurator->supports($metadata['fieldType'], $formFieldOptions, $metadata)) {
                    $formFieldOptions = $configurator->configure($name, $formFieldOptions, $metadata, $builder);
                }
            }

            $formFieldType = FormTypeHelper::getTypeClass($metadata['fieldType']);

            // if the form field is a special 'group' design element, don't add it
            // to the form. Instead, consider it the current form group (this is
            // applied to the form fields defined after it) and store its details
            // in a property to get them in form template
            if (in_array($formFieldType, ['harmony_admin_group', GroupType::class])) {
                $metadata['form_tab']          = $currentFormTab ?: null;
                $currentFormGroup              = $metadata['fieldName'];
                $formGroups[$currentFormGroup] = $metadata;

                continue;
            }

            // if the form field is a special 'tab' design element, don't add it
            // to the form. Instead, consider it the current form group (this is
            // applied to the form fields defined after it) and store its details
            // in a property to get them in form template
            if (\in_array($formFieldType, ['harmony_admin_tab', AdminTabType::class])) {
                // The first tab should be marked as active by default
                $metadata['active'] = 0 === \count($formTabs);
                $metadata['errors'] = 0;
                $currentFormTab     = $metadata['fieldName'];

                // plain arrays are not enough for tabs because they are modified in the
                // lifecycle of a form (e.g. add info about form errors). Use an ArrayObject instead.
                $formTabs[$currentFormTab] = new ArrayObject($metadata);

                continue;
            }

            // 'divider' and 'section' are 'fake' form fields used to create the design
            // elements of the complex form layouts: define them as unmapped and non-required
            if (0 === strpos($metadata['property'], '_harmony_admin_form_design_element_')) {
                $formFieldOptions['mapped']   = false;
                $formFieldOptions['required'] = false;
            }

            $formField = $builder->getFormFactory()->createNamedBuilder($name, $formFieldType, null, $formFieldOptions);
            $formField->setAttribute('harmony_admin_form_tab', $currentFormTab);
            $formField->setAttribute('harmony_admin_form_group', $currentFormGroup);

            $builder->add($formField);
        }

        $builder->setAttribute('harmony_admin_form_tabs', $formTabs);
        $builder->setAttribute('harmony_admin_form_groups', $formGroups);

        if (\count($formTabs) > 0) {
            $builder->addEventSubscriber(new HarmonyAdminTabSubscriber());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['harmony_admin_form_tabs']   = $form->getConfig()->getAttribute('harmony_admin_form_tabs');
        $view->vars['harmony_admin_form_groups'] = $form->getConfig()->getAttribute('harmony_admin_form_groups');
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $configManager = $this->configManager;

        $resolver->setDefaults([
            'allow_extra_fields' => true,
            'data_class'         => function (Options $options) use ($configManager) {
                $model       = $options['model'];
                $modelConfig = $configManager->getModelConfig($model);

                return $modelConfig['class'];
            },
        ])->setRequired(['model', 'view'])->setNormalizer('attr', $this->getAttributesNormalizer());
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'harmony_admin';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * Returns a closure normalizing the form html attributes.
     *
     * @return \Closure
     */
    private function getAttributesNormalizer()
    {
        return function (Options $options, $value) {
            return array_replace([
                'id' => sprintf('%s-%s-form', $options['view'], mb_strtolower($options['model'])),
            ], $value);
        };
    }
}
