<?php
namespace Cartography\Controller;

use Annotate\Api\Representation\AnnotationRepresentation;
use Annotate\Entity\Annotation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

abstract class AbstractCartographyController extends AbstractActionController
{
    /**
     * Get the images for a resource.
     *
     * @return JsonModel
     */
    public function imagesAction()
    {
        $resource = $this->resourceFromParams();
        if (!$resource) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'Not found.', // @translate
            ]);
        }

        $query = $this->params()->fromQuery();
        $images = $this->fetchImages($resource, $query);

        return new JsonModel([
            'status' => 'success',
            'resourceId' => $resource->id(),
            'images' => $images,
        ]);
    }

    /**
     * Get wms layers of a resource.
     *
     * @return JsonModel
     */
    public function wmsLayersAction()
    {
        $resource = $this->resourceFromParams();
        if (!$resource) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'Not found.', // @translate
            ]);
        }

        $query = $this->params()->fromQuery();
        $wmsLayers = $this->fetchWmsLayers($resource, $query);

        return new JsonModel([
            'status' => 'success',
            'resourceId' => $resource->id(),
            'wmsLayers' => $wmsLayers,
        ]);
    }

    /**
     * Get the geometries for a resource.
     *
     * @return JsonModel
     */
    public function geometriesAction()
    {
        $resource = $this->resourceFromParams();
        if (!$resource) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'Not found.', // @translate
            ]);
        }

        $query = $this->params()->fromQuery();
        $geometries = $this->fetchGeometries($resource, $query);

        return new JsonModel([
            'status' => 'success',
            'resourceId' => $resource->id(),
            'geometries' => $geometries,
        ]);
    }

    /**
     * Get the resource from the params of the request.
     *
     * @return \Omeka\Api\Representation\AbstractResourceEntityRepresentation|null
     */
    protected function resourceFromParams()
    {
        $id = $this->params('id');
        if (!$id) {
            return;
        }

        try {
            $resource = $this->api()->read('resources', $id)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return;
        }

        return $resource;
    }

    /**
     * Annotate a resource via ajax.
     *
     * @todo How to manage options? Some are related to target (quality of the
     * stroke), other to the body (the color may have a meaning). Use refinement?
     * Styled by / Style class (4.4)? Specific resource? Add a svg selector (but
     * not an svg)? And 4.2.7 : no styling for svg selector.
     * For now, kept with target with style class "leaflet-interactive" and
     * options are styles (the model allows it, but have only one class for css
     * currently, and a unusable upper class oa:Style: oa:SvgStyle is missing).
     * See representation.
     * @todo Same issue with the wkt selector, that doesn't exist: the generic
     * class oa:Selector should not be used, but oa:WktSelector doesn't exist.
     *
     * @todo Make Annotation a non-resource entity, so different api? See representation.
     *
     * @todo Check rights.
     */
    public function annotateAction()
    {
        $isAjax = $this->getRequest()->isXmlHttpRequest();
        if (!$isAjax) {
            return $this->notAjax();
        }

        $isPost = $this->getRequest()->isPost();
        if (!$isPost) {
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        $data = $this->params()->fromPost();

        if (empty($data['wkt'])) {
            return $this->jsonError('An internal error occurred from the client: no wkt.', Response::STATUS_CODE_400); // @translate
        }
        $geometry = $this->checkAndCleanWkt($data['wkt']);
        if (strlen($geometry) == 0) {
            return $this->jsonError('An internal error occurred from the client: unmanaged wkt .', Response::STATUS_CODE_400); // @translate
        }

        $api = $this->viewHelpers()->get('api');

        // Options contains styles and description.
        $options = isset($data['options']) ? $data['options'] : [];

        // Default motivation for the module Cartography is "highlighting" and a
        // motivation is required.
        if (empty($data['options']['oaMotivatedBy'])) {
            $options['oaMotivatedBy'] = 'highlighting';
        }
        // Clean the motivation when it is linking without link.
        elseif ($data['options']['oaMotivatedBy'] === 'linking' && empty($data['options']['oaLinking'])) {
            $options['oaMotivatedBy'] = 'highlighting';
        }
        // Multiple motivations are managed in actions.

        if (empty($data['id'])) {
            if (empty($data['resourceId'])) {
                return $this->jsonError('An internal error occurred from the client: no resource.', Response::STATUS_CODE_400); // @translate
            }

            $resourceId = $data['resourceId'];
            try {
                $resource = $api->read('resources', ['id' => $resourceId])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return $this->jsonError('Resource not found.', Response::STATUS_CODE_404); // @translate
            }

            // Save media id too to manage multiple media by image, else wms.
            $options['mediaId'] = empty($data['mediaId']) ? null : $data['mediaId'];
            if ($options['mediaId']) {
                $media = $api
                    ->searchOne('media', ['id' => $options['mediaId']])
                    ->getContent();
                if (!$media) {
                    return $this->jsonError('Media not found.', Response::STATUS_CODE_404); // @translate
                }
            }

            return $this->createAnnotation($resource, $geometry, $options);
        }

        $annotation = $api
            ->searchOne('annotations', ['id' => $data['id']])
            ->getContent();
        if (!$annotation) {
            return $this->jsonError('Annotation not found.', Response::STATUS_CODE_404); // @translate
        }

        // Save media id too to manage multiple media by image, else wms.
        $options['mediaId'] = empty($data['mediaId']) ? null : $data['mediaId'];

        return $this->updateAnnotation($annotation, $geometry, $options);
    }

    /**
     * Annotate a resource via ajax.
     *
     * @todo Check rights.
     */
    public function deleteAnnotationAction()
    {
        $isAjax = $this->getRequest()->isXmlHttpRequest();
        if (!$isAjax) {
            return $this->notAjax();
        }

        // TODO Use "Delete" instead of "Post".
        $isPost = $this->getRequest()->isPost();
        if (!$isPost) {
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        $data = $this->params()->fromPost();
        if (empty($data['id'])) {
            return $this->jsonError('An internal error occurred from the client: resource id not set.', Response::STATUS_CODE_400); // @translate
        }

        $id = $data['id'];

        $api = $this->viewHelpers()->get('api');
        $resource = $api
            ->searchOne('annotations', ['id' => $id])
            ->getContent();
        if (!$resource) {
            return $this->jsonError('Resource not found.', Response::STATUS_CODE_404); // @translate
        }

        if (!$resource->userIsAllowed('delete')) {
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        $this->api()->delete('annotations', $id);

        return new JsonModel([
            'status' => 'success',
            'result' => true,
        ]);
    }

    /**
     * Create a cartographic annotation with geometry.
     *
     * @todo Geojson is used for display, so create a table for wkt for quick access.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param string $geometry
     * @param array $options
     * @return \Zend\View\Model\JsonModel
     */
    protected function createAnnotation(AbstractResourceEntityRepresentation $resource, $geometry, array $options)
    {
        $api = $this->api();
        $data = [
            'o:is_public' => 1,
            'o:resource_template' => ['o:id' => $api->searchOne('resource_templates', ['label' => 'Annotation'])->getContent()->id()],
            'o:resource_class' => ['o:id' => $api->searchOne('resource_classes', ['term' => 'oa:Annotation'])->getContent()->id()],
            'o-module-annotate:body' => [],
            'o-module-annotate:target' => [
                [
                    'oa:hasSource' => [
                        [
                            'property_id' => $this->propertyId('oa:hasSource'),
                            'type' => 'resource',
                            'value_resource_id' => $resource->id(),
                        ],
                    ],
                ],
            ],
        ];

        // The media id should be added, if any. It is added first only for ux.
        $hasMediaId = !empty($options['mediaId']);
        if ($hasMediaId) {
            $data['o-module-annotate:target'][0]['rdf:value'][] = [
                'property_id' => $this->propertyId('rdf:value'),
                'type' => 'resource',
                'value_resource_id' => $options['mediaId'],
            ];
            unset($options['mediaId']);
        }

        $data['o-module-annotate:target'][0] += [
            // Currently, selectors are managed as a type internally.
            'rdf:type' => [
                [
                    'property_id' => $this->propertyId('rdf:type'),
                    'type' => 'customvocab:' . $this->customVocabId('Annotation Target rdf:type'),
                    // TODO Or oa:WKT, that doesn't exist? oa:Selector or Selector?
                    '@value' => 'oa:Selector',
                ],
            ],
            'dcterms:format' => [
                [
                    'property_id' => $this->propertyId('dcterms:format'),
                    'type' => 'customvocab:' . $this->customVocabId('Annotation Target dcterms:format'),
                    '@value' => 'application/wkt',
                ]
            ],
        ];

        $data['o-module-annotate:target'][0]['rdf:value'][] = [
            'property_id' => $this->propertyId('rdf:value'),
            'type' => 'literal',
            '@value' => $geometry,
        ];

        if ($options) {
            if (!empty($options['oaMotivatedBy'])) {
                $data['oa:motivatedBy'][] = [
                    'property_id' => $this->propertyId('oa:motivatedBy'),
                    'type' => 'customvocab:' . $this->customVocabId('Annotation oa:motivatedBy'),
                    '@value' => $options['oaMotivatedBy'],
                ];
            }

            if (isset($options['popupContent']) && strlen(trim($options['popupContent']))) {
                $data['o-module-annotate:body'][0]['rdf:value'][] = [
                    'property_id' => $this->propertyId('rdf:value'),
                    'type' => 'literal',
                    '@value' => $options['popupContent'],
                ];

                $data['o-module-annotate:body'][0]['oa:hasPurpose'] = [];
                if (!empty($options['oaHasPurpose'])) {
                    $data['o-module-annotate:body'][0]['oa:hasPurpose'][] = [
                        'property_id' => $this->propertyId('oa:hasPurpose'),
                        'type' => 'customvocab:' . $this->customVocabId('Annotation Body oa:hasPurpose'),
                        '@value' => $options['oaHasPurpose'],
                    ];
                }
            }

            if (!empty($options['oaLinking'])) {
                // A link is motivated by linking. Remove the other motivation
                // if there is no description.
                if (!empty($data['oa:motivatedBy'][0]) && empty($data['o-module-annotate:body'][0]['rdf:value'])) {
                    unset($data['oa:motivatedBy'][0]);
                    unset($data['o-module-annotate:body'][0]['oa:hasPurpose']);
                }

                $data['oa:motivatedBy'][] = [
                    'property_id' => $this->propertyId('oa:motivatedBy'),
                    'type' => 'customvocab:' . $this->customVocabId('Annotation oa:motivatedBy'),
                    '@value' => 'linking',
                ];
                // Each link is a separate body. Deduplicate ids too.
                $ids = [];
                foreach ($options['oaLinking'] as $valueResource) {
                    $id = $valueResource['value_resource_id'];
                    if (in_array($id, $ids)) {
                        continue;
                    }
                    $ids[] = $id;
                    $oaLinkingValues = [];
                    // The annotation id is unknown.
                    // $oaLinkingValues['o-module-annotate:annotation']['o:id'] = $annotation->id();
                    $oaLinkingValues['rdf:value'][] = [
                        'property_id' => $this->propertyId('rdf:value'),
                        'type' => 'resource',
                        'value_resource_id' => $id,
                    ];
                    $data['o-module-annotate:body'][] = $oaLinkingValues;
                }
            }

            $data['o-module-annotate:target'][0]['cartography:uncertainty'] = [];
            if (!empty($options['cartographyUncertainty'])) {
                $data['o-module-annotate:target'][0]['cartography:uncertainty'][] = [
                    'property_id' => $this->propertyId('cartography:uncertainty'),
                    'type' => 'customvocab:' . $this->customVocabId('Cartography cartography:uncertainty'),
                    '@value' => $options['cartographyUncertainty'],
                ];
            }

            unset($options['annotationIdentifier']);
            unset($options['oaMotivatedBy']);
            unset($options['popupContent']);
            unset($options['oaHasPurpose']);
            unset($options['oaLinking']);
            unset($options['cartographyUncertainty']);
            unset($options['owner']);
            unset($options['date']);

            if (!empty($options)) {
                $data['oa:styledBy'][] = [
                    'property_id' => $this->propertyId('oa:styledBy'),
                    'type' => 'literal',
                    '@value' => json_encode(['leaflet-interactive' => $options], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
                $data['o-module-annotate:target'][0]['oa:styleClass'][] = [
                    'property_id' => $this->propertyId('oa:styleClass'),
                    'type' => 'literal',
                    '@value' => 'leaflet-interactive',
                ];
            }
        }

        $response = $api->create('annotations', $data);
        if (!$response) {
            return $this->jsonError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        $annotation = $response->getContent();

        return new JsonModel([
            'status' => 'success',
            'result' => [
                'id' => $annotation->id(),
                'moderation' => !$this->userIsAllowed(Annotation::class, 'update'),
                'resourceId' => $resource->id(),
                'annotation' => $annotation->getJsonLd(),
            ],
        ]);
    }

    /**
     * Update a cartographic annotation with geometry.
     *
     * @todo Factorize createAnnotation() and updateAnnotation().
     *
     * @param AnnotationRepresentation $annotation
     * @param string $geometry
     * @param array $options
     * @return \Zend\View\Model\JsonModel
     */
    protected function updateAnnotation(AnnotationRepresentation $annotation, $geometry, array $options)
    {
        $api = $this->api();

        // Multiple bodies are managed because of multiple links can be created.
        $bodies = $annotation->bodies();
        // TODO One target is managed currently.
        $target = $annotation->primaryTarget();

        $data = [];
        $data['o-module-annotate:body'] = [];
        $data['o-module-annotate:target'] = [];

        // TODO The media id is not updatable, but is needed for partial update.
        $hasMediaId = !empty($options['mediaId']);
        if ($hasMediaId) {
            $data['o-module-annotate:target'][0]['rdf:value'][] = [
                'property_id' => $this->propertyId('rdf:value'),
                'type' => 'resource',
                'value_resource_id' => $options['mediaId'],
            ];
            unset($options['mediaId']);
        }

        // The selector type and format are kept from the target, but the
        // geometry can be updated.

        $data['o-module-annotate:target'][0]['rdf:value'][] = [
            'property_id' => $this->propertyId('rdf:value'),
            'type' => 'literal',
            '@value' => $geometry,
        ];

        // With leafllet, there are always options.
        if ($options) {
            if (!empty($options['oaMotivatedBy'])) {
                $data['oa:motivatedBy'][] = [
                    'property_id' => $this->propertyId('oa:motivatedBy'),
                    'type' => 'customvocab:' . $this->customVocabId('Annotation oa:motivatedBy'),
                    '@value' => $options['oaMotivatedBy'],
                ];
            }

            if (isset($options['popupContent']) && strlen(trim($options['popupContent']))) {
                $data['o-module-annotate:body'][0]['o-module-annotate:annotation']['o:id'] = $annotation->id();
                $data['o-module-annotate:body'][0]['rdf:value'][] = [
                    'property_id' => $this->propertyId('rdf:value'),
                    'type' => 'literal',
                    '@value' => $options['popupContent'],
                ];

                $data['o-module-annotate:body'][0]['oa:hasPurpose'] = [];
                if (!empty($options['oaHasPurpose'])) {
                    $data['o-module-annotate:body'][0]['oa:hasPurpose'][] = [
                        'property_id' => $this->propertyId('oa:hasPurpose'),
                        'type' => 'customvocab:' . $this->customVocabId('Annotation Body oa:hasPurpose'),
                        '@value' => $options['oaHasPurpose'],
                    ];
                }
            }

            if (!empty($options['oaLinking'])) {
                // A link is motivated by linking. Remove the other motivation
                // if there is no description.
                if (!empty($data['oa:motivatedBy'][0]) && empty($data['o-module-annotate:body'][0]['rdf:value'])) {
                    unset($data['oa:motivatedBy'][0]);
                    unset($data['o-module-annotate:body'][0]['oa:hasPurpose']);
                }
                $data['oa:motivatedBy'][] = [
                    'property_id' => $this->propertyId('oa:motivatedBy'),
                    'type' => 'customvocab:' . $this->customVocabId('Annotation oa:motivatedBy'),
                    '@value' => 'linking',
                ];
                // Each link is a separate body. Deduplicate ids too.
                $ids = [];
                foreach ($options['oaLinking'] as $valueResource) {
                    $id = $valueResource['value_resource_id'];
                    if (in_array($id, $ids)) {
                        continue;
                    }
                    $ids[] = $id;
                    $oaLinkingValues = [];
                    $oaLinkingValues['o-module-annotate:annotation']['o:id'] = $annotation->id();
                    $oaLinkingValues['rdf:value'][] = [
                        'property_id' => $this->propertyId('rdf:value'),
                        'type' => 'resource',
                        'value_resource_id' => $id,
                    ];
                    $data['o-module-annotate:body'][] = $oaLinkingValues;
                }
            }

            $data['o-module-annotate:target'][0]['cartography:uncertainty'] = [];
            if (!empty($options['cartographyUncertainty'])) {
                $data['o-module-annotate:target'][0]['cartography:uncertainty'][] = [
                    'property_id' => $this->propertyId('cartography:uncertainty'),
                    'type' => 'customvocab:' . $this->customVocabId('Cartography cartography:uncertainty'),
                    '@value' => $options['cartographyUncertainty'],
                ];
            }

            // TODO Check if original and editing are the same to avoid update or to create a useless style.
            unset($options['original']);
            unset($options['editing']);
            unset($options['annotationIdentifier']);
            unset($options['oaMotivatedBy']);
            unset($options['popupContent']);
            unset($options['oaHasPurpose']);
            unset($options['oaLinking']);
            unset($options['cartographyUncertainty']);
            unset($options['owner']);
            unset($options['date']);

            // TODO Don't update style if it is not updated (so it can be kept empty). And reset it eventually. And use class style.
            if (!empty($options)) {
                $data['oa:styledBy'][] = [
                    'property_id' => $this->propertyId('oa:styledBy'),
                    'type' => 'literal',
                    '@value' => json_encode(['leaflet-interactive' => $options], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ];
                $data['o-module-annotate:target'][0]['oa:styleClass'][] = [
                    'property_id' => $this->propertyId('oa:styleClass'),
                    'type' => 'literal',
                    '@value' => 'leaflet-interactive',
                ];
            }
        }

        // Partial update is complex, so reload the full annotation.
        // $response = $api->update('annotations', $annotation->id(), $data, [], ['isPartial' => true]);

        // Update main annotation first.
        if ($options) {
            $values = $this->arrayValues($annotation);
            if (!empty($data['oa:styledBy'])) {
                $values['oa:styledBy'] = $data['oa:styledBy'];
            }
            if (!empty($data['oa:motivatedBy'])) {
                $values['oa:motivatedBy'] = $data['oa:motivatedBy'];
            }
            $response = $api->update('annotations', $annotation->id(), $values, [], ['isPartial' => true]);
        }

        // Save the bodies separately, if any.
        // This is possible because the annotation was updated partially.
        // Because deduplication between existing and new values is complex,
        // simply delete existing bodies and create new ones.
        foreach ($bodies as &$body) {
            $body = $body->id();
        }
        unset($body);
        $response = $api->batchDelete('annotation_bodies', $bodies);
        $response = $api->batchCreate('annotation_bodies', $data['o-module-annotate:body']);

        // There is always one target at least, and only one is managed.
        $values = $this->arrayValues($target);
        $values['rdf:value'] = $data['o-module-annotate:target'][0]['rdf:value'];
        if ($options) {
            $values['oa:styleClass'] = $data['o-module-annotate:target'][0]['oa:styleClass'];
            $values['cartography:uncertainty'] = $data['o-module-annotate:target'][0]['cartography:uncertainty'];
        }
        $response = $api->update('annotation_targets', $target->id(), $values, [], ['isPartial' => true]);

        if (!$response) {
            return $this->jsonError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        return new JsonModel([
            'status' => 'success',
            'result' => [
                'id' => $annotation->id(),
                'moderation' => !$this->userIsAllowed(Annotation::class, 'update'),
                // 'resourceId' => $resource->id(),
                'annotation' => $annotation->getJsonLd(),
            ],
        ]);
    }

    /**
     * Prepare all images (url and size) for a resource.
     *
     * @todo Manage tiles, iiif, etc.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $params Params to specify the images:
     * - type (string): type of the image (default: "original").
     * @return array Array of images data.
     */
    protected function fetchImages(AbstractResourceEntityRepresentation $resource, array $params = [])
    {
        $images = [];
        $resourceName = $resource->resourceName();
        switch ($resourceName) {
            case 'items':
                $medias = $resource->media();
                break;
            case 'media':
                $medias = [$resource];
                break;
            default:
                return $images;
        }

        $imageType = isset($params['type']) ? $params['type'] : null;
        foreach ($medias as $media) {
            if (!$media->hasOriginal()) {
                continue;
            }
            $size = $this->imageSize($media, $imageType);
            if (!$size) {
                continue;
            }
            $image = [];
            $image['id'] = $media->id();
            $image['url'] = $media->originalUrl();
            $image['size'] = array_values($size);
            $images[] = $image;
        }

        return $images;
    }

    /**
     * Prepare all wms layers for a resource (uri provided as dcterms:spatial).
     *
     * The list is deduplicated.
     *
     * @todo Use a unique doctrine query to get all the dctems:spatial uris values, with automatic deduplication.
     * @todo Add an option to deduplicate or to identify the level.
     * @todo Make this method a recursive method (not important currently).
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $params Params to specify the wms layers:
     * - upper (int): add wms layers of the upper level. 0 (default) means no,
     * 1 means item for media, item set for item), 2 means first level and item
     * set for media.
     * - lower (int): add wms layers of the lower level. 0 (default) means no,
     * 1 means media for item, item for item set), 2 means first level and media
     * for item set.
     * @return array Array of wms layers data.
     */
    protected function fetchWmsLayers(AbstractResourceEntityRepresentation $resource, array $params = [])
    {
        $wmsLayers = [];

        // Add upper level first.
        $resourceName = $resource->resourceName();
        $upper = empty($params['upper']) ? 0 : $params['upper'];
        $lower = empty($params['lower']) ? 0 : $params['lower'];

        if ($upper) {
            switch ($resourceName) {
                case 'items':
                    foreach ($resource->itemSets() as $itemSet) {
                        $wmsLayers = array_merge($wmsLayers, $this->extractWmsLayers($itemSet));
                    }
                    break;
                case 'media':
                    $item = $resource->item();
                    if ($upper == 2) {
                        foreach ($item->itemSets() as $itemSet) {
                            $wmsLayers = array_merge($wmsLayers, $this->extractWmsLayers($itemSet));
                        }
                    }
                    $wmsLayers = array_merge($wmsLayers, $this->extractWmsLayers($item));
                    break;
            }
        }

        $wmsLayers = array_merge($wmsLayers, $this->extractWmsLayers($resource));

        if ($lower) {
            switch ($resourceName) {
                case 'item_sets':
                    foreach ($resource->items() as $item) {
                        $wmsLayers = array_merge($wmsLayers, $this->extractWmsLayers($item));
                        if ($lower == 2) {
                            foreach ($item->media() as $media) {
                                $wmsLayers = array_merge($wmsLayers, $this->extractWmsLayers($media));
                            }
                        }
                    }
                    break;
                case 'items':
                    foreach ($resource->media() as $media) {
                        $wmsLayers = array_merge($wmsLayers, $this->extractWmsLayers($media));
                    }
                    break;
            }
        }

        return array_values($wmsLayers);
    }

    /**
     * Get wms layers for a resource (uri provided as dcterms:spatial).
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array Array of wms layers data, mapped by url.
     */
    protected function extractWmsLayers(AbstractResourceEntityRepresentation $resource)
    {
        $wmsLayers = [];
        $values = $resource->value('dcterms:spatial', ['type' => 'uri', 'all' => true, 'default' => []]);
        foreach ($values as $value) {
            $url = $value->uri();
            if (parse_url($url)) {
                $wmsLayer = [
                    'url' => $url,
                    'label' => $value->value(),
                ];
                $wmsLayers[$url] = $wmsLayer;
            }
        }
        return $wmsLayers;
    }

    /**
     * Prepare all geometries for a resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $query Query to specify the geometries. May have optional
     * arguments:
     * - mediaId: if integer greater than or equal to 1, get only the geometries
     * for that media; if equal to 0, get only geometries without media id; if
     * equal to -1, get all geometries with a media id; if not set, get all
     * geometries, whatever they have a media id or not.
     * - annotationId" to specify an annotation, else all annotations are
     * returned.
     * @return array Array of geometries.
     */
    protected function fetchGeometries(AbstractResourceEntityRepresentation $resource, array $query = [])
    {
        $geometries = [];

        $mediaId = array_key_exists('mediaId', $query)
            ? (int) $query['mediaId']
            : null;

        $annotationId = array_key_exists('annotationId', $query)
            ? (int) $query['annotationId']
            : null;

        /** @var \Annotate\Api\Representation\AnnotationRepresentation[] $annotations */
        $annotations = $this->resourceAnnotations($resource, $query);
        foreach ($annotations as $annotation) {
            // Filter annotation if annotation id is set.
            if ($annotationId && $annotationId !== $annotation->id()) {
                continue;
            }

            // Currently, only one target by annotation.
            $target = $annotation->primaryTarget();
            if (!$target) {
                continue;
            }

            $format = $target->value('dcterms:format');
            if (empty($format) || $format->value() !== 'application/wkt') {
                continue;
            }

            $geometry = [];
            $geometry['id'] = $annotation->id();

            $values = $target->value('rdf:value', ['all' => true, 'default' => []]);
            foreach ($values as $value) {
                if ($value->type() === 'resource') {
                    $geometry['mediaId'] = $value->valueResource()->id();
                } else {
                    // TODO Only one target is managed currently.
                    $geometry['wkt'] = $value->value();
                }
            }

            if (empty($geometry['wkt'])) {
                continue;
            }

            if ($mediaId === 0 && !empty($geometry['mediaId'])) {
                continue;
            }
            if ($mediaId) {
                if (empty($geometry['mediaId'])) {
                    continue;
                }
                if ($mediaId > 0 && $mediaId !== $geometry['mediaId']) {
                    continue;
                }
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

            // Only two motivations are managed together currently: a default
            // one and "linking". Because linking is automatically set, only the
            // other motivation is needed.
            $geometry['options']['oaMotivatedBy'] = '';
            $values = $annotation->value('oa:motivatedBy', ['all' => true, 'default' => []]);
            foreach ($values as $value) {
                $geometry['options']['oaMotivatedBy'] = $value->value();
                if ($geometry['options']['oaMotivatedBy'] !== 'linking') {
                    break;
                }
            }

            // Default values.
            $geometry['options']['popupContent'] = '';
            $geometry['options']['oaHasPurpose'] = '';
            $geometry['options']['oaLinking'] = [];

            $bodies = $annotation->bodies();
            foreach ($bodies as $body) {
                // Only one description is managed by geometry, except motivated
                // by linking, in which case the value is an Omeka resource or
                // an uri.
                // There is only one value by body.
                $value = $body->value('rdf:value');
                if (!$value) {
                    continue;
                }
                if ($value->type() === 'resource') {
                    /** @var \Omeka\Api\Representation\ItemRepresentation $valueResource */
                    $valueResource = $value->valueResource();
                    // The url of the value representation is empty, but the @id
                    // contains the url for api. No issue for admin and public.
                    $geometry['options']['oaLinking'][] = $valueResource->valueRepresentation();
                } else {
                    // TODO Manage body values as uris too (here, the popup content may be converted into a pure value).
                    $geometry['options']['popupContent'] = $value->type() === 'uri' ? $value->uri() : $value->value();
                    $value = $body->value('oa:hasPurpose');
                    $geometry['options']['oaHasPurpose'] = $value ? $value->value() : '';
                }
            }

            $value = $target->value('cartography:uncertainty');
            $geometry['options']['cartographyUncertainty'] = $value ? $value->value() : '';

            // Get the author and the date to fill the popup.
            $owner = $annotation->owner();
            $geometry['options']['owner'] = [
                'id' => $owner->id(),
                'name' => $owner->name(),
            ];
            $geometry['options']['date'] = $annotation->created()->format('Y-m-d H:i:s');

            $geometries[] = $geometry;
        }

        return $geometries;
    }

    protected function propertyId($term)
    {
        $api = $this->viewHelpers()->get('api');
        $result = $api->searchOne('properties', ['term' => $term])->getContent();
        return $result ? $result->id() : null;
    }

    protected function customVocabId($label)
    {
        $api = $this->viewHelpers()->get('api');
        $result = $api->read('custom_vocabs', ['label' => $label])->getContent();
        return $result ? $result->id() : null;
    }

    /**
     * Convert the values of a resource into an array.
     *
     * @todo Manage the specific data.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function arrayValues(AbstractResourceEntityRepresentation $resource = null)
    {
        if (empty($resource)) {
            return [];
        }

        $result = [];
        foreach ($resource->values() as $term => $values) {
            $termId = $values['property']->id();
            /** @var \Omeka\Api\Representation\ValueRepresentation $value */
            foreach ($values['values'] as $value) {
                $arrayValue = [
                    'property_id' => $termId,
                    'type' => $value->type(),
                ];
                switch ($value->type()) {
                    case 'uri':
                        $arrayValue['@id'] = $value->uri();
                        $arrayValue['o:label'] = $value->value() ?: null;
                        break;
                    case 'resource':
                        $arrayValue['value_resource_id'] = $value->valueResource()->id();
                        break;
                    case 'literal':
                    default:
                        $arrayValue['language'] = $value->lang() ?: null;
                        $arrayValue['@value'] = $value->value();
                        break;
                }
                $result[$term][] = $arrayValue;
            }
        }
        return $result;
    }

    /**
     * Check and cliean a wkt string.
     *
     * @todo Find a better way to check if a string is a wkt.
     *
     * @param string $string
     * @return string|null
     */
    protected function checkAndCleanWkt($string)
    {
        $string = trim($string);
        if (strlen($string) == 0) {
            return;
        }
        $wktTags = [
            'GEOMETRY',
            'POINT',
            'LINESTRING',
            'POLYGON',
            'MULTIPOINT',
            'MULTILINESTRING',
            'MULTIPOLYGON',
            'GEOMETRYCOLLECTION',
            'CIRCULARSTRING',
            'COMPOUNDCURVE',
            'CURVEPOLYGON',
            'MULTICURVE',
            'MULTISURFACE',
            'CURVE',
            'SURFACE',
            'POLYHEDRALSURFACE',
            'TIN',
            'TRIANGLE',
            'CIRCLE',
            'CIRCLEMARKER',
            'GEODESICSTRING',
            'ELLIPTICALCURVE',
            'NURBSCURVE',
            'CLOTHOID',
            'SPIRALCURVE',
            'COMPOUNDSURFACE',
            'BREPSOLID',
            'AFFINEPLACEMENT',
        ];
        // Get first word to check wkt.
        $firstWord = strtoupper(strtok($string, " (\n\r"));
        if (strpos($string, '(') && in_array($firstWord, $wktTags)) {
            return $string;
        }
    }

    protected function jsonError($message, $statusCode = Response::STATUS_CODE_500)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        return new JsonModel([
            'status' => 'error',
            'message' => $message,
        ]);
    }

    abstract protected function notAjax();
}
