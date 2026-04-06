<?php declare(strict_types=1);

namespace Cartography\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
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
                'name' => 'cartography_append_public',
                'type' => Element\MultiCheckbox::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
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
            ])
            ->add([
                'name' => 'cartography_annotate_describe',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Resource block Describle: Enable annotation', // @translate
                ],
                'attributes' => [
                    'id' => 'cartography_annotate_describe',
                ],
            ])
            ->add([
                'name' => 'cartography_annotate_locate',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Resource block Locate: Enable annotation', // @translate
                ],
                'attributes' => [
                    'id' => 'cartography_annotate_locate',
                ],
            ]);
    }
}
