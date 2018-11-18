<?php
namespace Cartography\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    public function init()
    {
        $this->setLabel('Cartography (annotate images and maps)'); // @translate

        $this->add([
            'name' => 'cartography_append_public',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Append to pages', // @translate
                'info' => 'If unchecked, the viewer can be added via the helper in the theme or the block in any page.', // @translate
                'value_options' => [
                    // 'describe_item_sets_show' => 'Describe item set', // @translate
                    'describe_items_show' => 'Describe item', // @translate
                    // 'describe_media_show' => 'Describe media', // @translate
                    // 'locate_item_sets_show' => 'Locate item set', // @translate
                    'locate_items_show' => 'Locate item', // @translate
                    // 'locate_media_show' => 'Locate media', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'cartography_append_public',
            ],
        ]);

        // $fieldset->add([
        //     'name' => 'cartography_annotate',
        //     'type' => Element\Checkbox::class,
        //     'options' => [
        //         'label' => 'Enable annotation', // @translate
        //         'info' => 'Allows to enable/disable the image/map annotation on this specific site. In all cases, the rights are defined by the module Annotate.', // @translate
        //     ],
        //     'attributes' => [
        //         'id' => 'cartography_annotate',
        //     ],
        // ]);
    }
}
