<?php
namespace Cartography\Form;

use Omeka\Form\Element\CkeditorInline;
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
                    'describe' => 'Describe: annotate the first image of the item', // @translate
                    'locate' => 'Locate: highlight elements on a map', // @translate
                    // TODO Make a choice to annotate at media level when they are multiple images.
                ],
            ],
            'attributes' => [
                'id' => 'cartography_display_tab',
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
