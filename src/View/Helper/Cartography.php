<?php
namespace Cartography\View\Helper;

use Annotate\Mvc\Controller\Plugin\ResourceAnnotations;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

class Cartography extends AbstractHelper
{
    /**
     * @var ResourceAnnotations
     */
    protected $resourceAnnotationsPlugin;

    public function __construct(ResourceAnnotations $resourceAnnotationsPlugin)
    {
        $this->resourceAnnotationsPlugin = $resourceAnnotationsPlugin;
    }

    /**
     * Return the partial to display cartography.
     *
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $resourceAnnotationsPlugin = $this->resourceAnnotationsPlugin;
        // TODO ResourceAnnotations doesn't know to search properties on targets and bodies.
        $annotations = $resourceAnnotationsPlugin($resource);
        $geometries = [];

        // TODO Use the rdf format of annotation to find geometry quickly?
        foreach ($annotations as $annotation) {
            // TODO There is only one target by annotation currently.
            $target = $annotation->primaryTarget();
            if (!$target) {
                continue;
            }

            $format = $target->value('dcterms:format');
            if (empty($format)) {
                continue;
            }

            $geometry = [];
            $format = $format->value();
            if ($format === 'application/wkt') {
                $value = $target->value('rdf:value');
                $geometry['id'] = $annotation->id();
                $geometry['wkt'] = $value ? $value->value() : null;
            }

            $styleClass = $target->value('oa:styleClass');
            if ($styleClass && $styleClass->value() === 'leaflet-interactive') {
                $options = $annotation->value('oa:styledBy');
                if ($options) {
                    $options = json_decode($options->value(), true);
                    if (!empty($options['leaflet-interactive'])) {
                        $geometry['options'] = $options['leaflet-interactive'];
                    }
                }
            }

            $geometries[$annotation->id()] = $geometry;
        }

        echo $this->getView()->partial(
            'common/site/cartography',
            [
                'resource' => $resource,
                'geometries' => $geometries,
            ]
        );
    }
}
