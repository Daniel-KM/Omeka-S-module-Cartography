<?php declare(strict_types=1);

namespace Cartography\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element\CkeditorInline;
use Omeka\Form\Element\ResourceTemplateSelect;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Cartography (annotate images and maps)'; // @translate

    protected $elementGroups = [
        'annotate_cartography' => 'Annotate cartography', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'cartography')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'cartography_user_guide',
                'type' => CkeditorInline::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'User guide', // @translate
                    'info' => 'This text will be shown below the cartography.', // @translate
                ],
                'attributes' => [
                    'id' => 'cartography_user_guide',
                    'placeholder' => 'Feel free to use cartography!', // @translate
                ],
            ])

            ->add([
                'name' => 'cartography_display_tab',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Tabs to display in resource form', // @translate
                    'value_options' => [
                        'describe' => 'Describe: annotate images of the item', // @translate
                        'locate' => 'Locate: annotate georeferenced wms (dcterms:spatial) of the item', // @translate
                    ],
                ],
                'attributes' => [
                        'id' => 'cartography_display_tab',
                    ],
                ])

            // The default values are automatically appended to the availalble
            // values on submit via js.

            ->add([
                'name' => 'cartography_template_describe',
                'type' => ResourceTemplateSelect::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Templates to use for Describe', // @translate
                    'info' => 'Allow to preset different properties to simplify cartography. If none, only the style editor will be available.', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                    'query' => [
                        'resource_class' => 'oa:Annotation',
                    ],
                ],
                'attributes' => [
                    'id' => 'cartography_template_describe',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select templates to describe…', // @translate
                ],
            ])

            ->add([
                'name' => 'cartography_template_describe_empty',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Empty form by default for Describe', // @translate
                    'info' => 'If checked, the user will have to choose a resource template first.', // @translate
                ],
                'attributes' => [
                    'id' => 'cartography_template_describe_empty',
                ],
            ])

            ->add([
                'name' => 'cartography_template_locate',
                'type' => ResourceTemplateSelect::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Templates to use for Locate', // @translate
                    'info' => 'Allow to preset different properties to simplify cartography. If none, only the style editor will be available.', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                    'query' => [
                        'resource_class' => 'oa:Annotation',
                    ],
                ],
                'attributes' => [
                    'id' => 'cartography_template_locate',
                    'class' => 'chosen-select',
                    'multiple' => true,
                    'data-placeholder' => 'Select templates to locate…', // @translate
                ],
            ])

            ->add([
                'name' => 'cartography_template_locate_empty',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Empty form by default for Locate', // @translate
                    'info' => 'If checked, the user will have to choose a resource template first.', // @translate
                ],
                'attributes' => [
                    'id' => 'cartography_template_locate_empty',
                ],
            ])

            ->add([
                'name' => 'cartography_js_describe',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Specific parameters for Describe (js)', // @translate
                    'info' => 'See readme.', // @translate
                ],
                'attributes' => [
                    'id' => 'cartography_js_describe',
                ],
            ])

            ->add([
                'name' => 'cartography_js_locate',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Specific parameters for Locate (js)', // @translate
                    'info' => 'This js code allows to replace the default maps. See readme.', // @translate
                ],
                'attributes' => [
                    'id' => 'cartography_js_locate',
                ],
            ]);
    }
}
