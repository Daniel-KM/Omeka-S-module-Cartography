<?php
namespace Cartography\DataType;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\AbstractDataType;
use Omeka\Entity\Value;
use Zend\Form\Element;
use Zend\View\Renderer\PhpRenderer;

class Geometry extends AbstractDataType
{
    public function getName()
    {
        return 'geometry';
    }

    public function getLabel()
    {
        return 'Geometry'; // @translate
    }

    public function getOptgroupLabel()
    {
        return 'Cartography'; // @translate
    }

    public function prepareForm(PhpRenderer $view)
    {
        $view->headLink()->appendStylesheet($view->assetUrl('css/cartography.css', 'Cartography'));
        $view->headScript()->appendFile($view->assetUrl('js/cartography-geometry-datatype.js', 'Cartography'));
    }

    public function form(PhpRenderer $view)
    {
        $element = new Element\Textarea('geometry');
        $element->setAttributes([
            'class' => 'value to-require geometry',
            'data-value-key' => '@value',
            'placeholder' => 'POINT (2.294497 48.858252)',
        ]);
        return $view->formTextarea($element);
    }

    public function isValid(array $valueObject)
    {
        return (bool) strlen(trim($valueObject['@value']));
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter)
    {
        $value->setValue(trim($valueObject['@value']));
        $value->setLang(null);
        $value->setUri(null);
        $value->setValueResource(null);
    }

    public function render(PhpRenderer $view, ValueRepresentation $value)
    {
        return (string) $value->value();
    }

    // public function toString(ValueRepresentation $value)
    // {
    //     return (string) $value->value();
    // }

    public function getJsonLd(ValueRepresentation $value)
    {
        return [
            '@value' => $value->value(),
        ];
    }
}
