<?php
namespace Cartography\DataType;

use Cartography\Doctrine\PHP\Types\Geometry\Geometry as GenericGeometry;
use CrEOF\Geo\WKT\Parser as GeoWktParser;
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
        try {
            $geometry = new GeoWktParser($valueObject['@value']);
            $geometry = $geometry->parse();
        } catch (\Exception $e) {
            return false;
        }
        return $geometry !== null;
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

    /**
     * GeoJSON Specification (RFC 7946) is not used: it is not fully compliant
     * with json-ld.
     * @see https://github.com/json-ld/json-ld.org/issues/397
     * @link https://tools.ietf.org/html/rfc7946
     *
     * {@inheritDoc}
     * @see \Omeka\DataType\DataTypeInterface::getJsonLd()
     */
    public function getJsonLd(ValueRepresentation $value)
    {
        return [
            '@value' => $value->value(),
            '@type' => 'http://geovocab.org/geometry#asWKT',
        ];
    }

    /**
     * Convert a string into a geometry representation.
     *
     * @param string $value Accept AbstractGeometry and geometry array too.
     * @throws \InvalidArgumentException
     * @return \CrEOF\Spatial\PHP\Types\AbstractGeometry
     */
    public function getGeometryFromValue($value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Empty geometry.'); // @translate
        }
        try {
            return (new GenericGeometry($value))->getGeometry();
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid geometry.'); // @translate
        }
    }
}
