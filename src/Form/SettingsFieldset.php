<?php
namespace Cartography\Form;

use Annotate\Form\Element\ResourceTemplateSelect;
use Omeka\Form\Element\CkeditorInline;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    protected $label = 'Cartography (annotate images and maps)'; // @translate

    public function init()
    {
        $this->add([
            'name' => 'cartography_user_guide',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'User guide', // @translate
                'info' => 'This text will be shown below the cartography.', // @translate
            ],
            'attributes' => [
                'id' => 'cartography_user_guide',
                'placeholder' => 'Feel free to use cartography!', // @translate
            ],
        ]);

        $this->add([
            'name' => 'cartography_display_tab',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Tabs to display', // @translate
                'value_options' => [
                    'describe' => 'Describe: annotate images of the item', // @translate
                    'locate' => 'Locate: annotate georeferenced wms (dcterms:spatial) of the item', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'cartography_display_tab',
            ],
        ]);

        // The default values are automatically appended to the availalble
        // values on submit via js.

        $this->add([
            'name' => 'cartography_template_describe',
            'type' => ResourceTemplateSelect::class,
            'options' => [
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
        ]);

        $this->add([
            'name' => 'cartography_template_describe_empty',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Empty form by default for Describe', // @translate
                'info' => 'If checked, the user will have to choose a resource template first.', // @translate
            ],
            'attributes' => [
                'id' => 'cartography_template_describe_empty',
            ],
        ]);

        $this->add([
            'name' => 'cartography_template_locate',
            'type' => ResourceTemplateSelect::class,
            'options' => [
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
        ]);

        $this->add([
            'name' => 'cartography_template_locate_empty',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Empty form by default for Locate', // @translate
                'info' => 'If checked, the user will have to choose a resource template first.', // @translate
            ],
            'attributes' => [
                'id' => 'cartography_template_locate_empty',
            ],
        ]);

        $this->add([
            'name' => 'cartography_js_describe',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Specific parameters for Describe (js)', // @translate
                'info' => 'See readme.', // @translate
            ],
            'attributes' => [
                'id' => 'cartography_js_describe',
            ],
        ]);

        $this->add([
            'name' => 'cartography_js_locate',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Specific parameters for Locate (js)', // @translate
                'info' => 'This js code allows to replace the default maps. See readme.', // @translate
            ],
            'attributes' => [
                'id' => 'cartography_js_locate',
            ],
        ]);
    }
}
