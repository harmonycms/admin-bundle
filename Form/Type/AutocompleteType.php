<?php

namespace Harmony\Bundle\AdminBundle\Form\Type;

use Harmony\Bundle\AdminBundle\Configuration\ConfigManager;
use Harmony\Bundle\AdminBundle\Form\EventListener\HarmonyAdminAutocompleteSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function array_splice;
use function current;
use function iterator_to_array;
use function sprintf;

/**
 * Autocomplete form type.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class AutocompleteType extends AbstractType implements DataMapperInterface
{

    private $configManager;

    /**
     * AutocompleteType constructor.
     *
     * @param ConfigManager $configManager
     */
    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new HarmonyAdminAutocompleteSubscriber())->setDataMapper($this);
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if (null === $config = $this->configManager->getModelConfigByClass($options['class'])) {
            throw new \InvalidArgumentException(sprintf('The configuration of the "%s" model is not available (this model is used as the target of the "%s" autocomplete field).',
                $options['class'], $form->getName()));
        }

        $view->vars['autocomplete_model_name'] = $config['name'];
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        // Add a custom block prefix to inner field to ease theming:
        array_splice($view['autocomplete']->vars['block_prefixes'], - 1, 0, 'harmony_admin_autocomplete_inner');
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'multiple'       => false,
            // force display errors on this form field
            'error_bubbling' => false,
        ]);

        $resolver->setRequired(['class']);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'harmony_admin_autocomplete';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms($data, $forms)
    {
        $form = current(iterator_to_array($forms));
        $form->setData($data);
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData($forms, &$data)
    {
        $form = current(iterator_to_array($forms));
        $data = $form->getData();
    }
}
