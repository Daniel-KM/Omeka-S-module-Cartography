<?php declare(strict_types=1);

namespace Cartography\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Cartography (annotate images and maps)'; // @translate

    protected $elementGroups = [
        'annotate_cartography' => 'Annotate cartography', // @translate
        'themes_old' => 'Old themes', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'cartography')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'cartography_annotate_describe',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'annotate_cartography',
                    'label' => 'Resource block Describe: Enable annotation', // @translate
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
            ])

            ->add([
                'name' => 'cartography_placement',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'themes_old',
                    'label' => 'Cartography (old themes)', // @translate
                    'value_options' => [
                        'after/items/describe' => 'Describe: item show', // @translate
                        'after/items/locate' => 'Locate: item show', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'cartography_placement',
                    'required' => false,
                ],
            ])
        ;
    }
}
