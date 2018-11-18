<?php
namespace Cartography\Form;

use Omeka\Form\Element\CkeditorInline;
use Cartography\Form\Element\ResourceTemplateSelect;
use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
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

        $this->add([
            'name' => 'cartography_template_describe',
            'type' => ResourceTemplateSelect::class,
            'options' => [
                'label' => 'Templates to use for Describe', // @translate
                'info' => 'Allow to preset different properties to simplify cartography. If none, only the style editor will be available.', // @translate
                'empty_option' => 'Select templates to annotate…', // @translate
                'name_as_value' => true,
                'term_as_value' => true,
            ],
            'attributes' => [
                'id' => 'cartography_template_describe',
                'class' => 'chosen-select',
                'multiple' => true,
                'data-placeholder' => 'Select templates to annotate…', // @translate
            ],
        ]);

        $this->add([
            'name' => 'cartography_template_locate',
            'type' => ResourceTemplateSelect::class,
            'options' => [
                'label' => 'Templates to use for Locate', // @translate
                'info' => 'Allow to preset different properties to simplify cartography. If none, only the style editor will be available.', // @translate
                'empty_option' => 'Select templates to annotate…', // @translate
                'name_as_value' => true,
                'term_as_value' => true,
            ],
            'attributes' => [
                'id' => 'cartography_template_locate',
                'class' => 'chosen-select',
                'multiple' => true,
                'data-placeholder' => 'Select templates to annotate…', // @translate
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

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'cartography_display_tab',
            'required' => false,
        ]);
    }
}
