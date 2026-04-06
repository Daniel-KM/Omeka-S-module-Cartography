<?php declare(strict_types=1);

namespace Cartography\Controller;

use Annotate\Api\Representation\AnnotationRepresentation;
use Common\Stdlib\PsrMessage;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

abstract class AbstractCartographyController extends AbstractActionController
{
    /**
     * Get the resource templates for a resource.
     *
     * @return JsonModel
     */
    public function resourceTemplatesAction()
    {
        $type = $this->params()->fromQuery('type');
        if (!in_array($type, ['describe', 'locate'])) {
            return $this->jsonError(new PsrMessage(
                'The arg "type" (describe or locate) was not found.' // @translate
            ), Response::STATUS_CODE_400);
        }

        $templates = $type === 'describe'
            ? $this->settings()->get('cartography_template_describe', [])
            : $this->settings()->get('cartography_template_locate', []);

        foreach ($templates as $key => $templateId) {
            $shortTemplate = $this->shortResourceTemplate($templateId);
            if (empty($shortTemplate)) {
                unset($templates[$key]);
                continue;
            }
            $templates[$key] = $shortTemplate;
        }

        if ($templates) {
            $emptyOption = $type === 'describe'
                ? $this->settings()->get('cartography_template_describe_empty')
                : $this->settings()->get('cartography_template_locate_empty');
            if ($emptyOption) {
                $templates = array_merge(
                    [['placeholder' => $this->translate('Select type…')]], // @translate
                    $templates
                );
            }
        }

        return new JsonModel($templates);
    }

    /**
     * Get the images for a resource.
     *
     * @return JsonModel
     */
    public function imagesAction()
    {
        $resource = $this->resourceFromParams();
        if (!$resource) {
            return $this->jsonError(new PsrMessage(
                'Not found.' // @translate
            ), Response::STATUS_CODE_404);
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
            return $this->jsonError(new PsrMessage(
                'Not found.' // @translate
            ), Response::STATUS_CODE_404);
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
     * Get the geometries for a resource, with simplified and partial metadata.
     *
     * Note: Metadata are simplified for the display and use in leaflet.
     *
     * @return JsonModel
     */
    public function geometriesAction()
    {
        $resource = $this->resourceFromParams();
        if (!$resource) {
            return $this->jsonError(new PsrMessage(
                'Not found.' // @translate
            ), Response::STATUS_CODE_404);
        }

        $query = $this->params()->fromQuery();

        $geometries = $this->fetchSimpleGeometries($resource, $query);

        return new JsonModel([
            'status' => 'success',
            'resourceId' => $resource->id(),
            'geometries' => $geometries,
        ]);
    }

    /**
     * Get the geometries of all georeferenced annotations for the geobrowse.
     *
     * @return JsonModel
     */
    public function browseGeometriesAction()
    {
        $query = $this->params()->fromQuery();
        $query['resource_class'] = 'oa:Annotation';
        $query['mediaId'] = 0;

        $geometries = $this->fetchSimpleGeometries(null, $query);

        return new JsonModel([
            'status' => 'success',
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
            return null;
        }

        return $resource;
    }

    public function browseAction()
    {
        $this->browse()->setDefaults('annotations');

        $query = $this->params()->fromQuery();

        // TODO Force wkt to simplify cartographic query.
        // $query['property'][] = [
        //     'property' => 'dcterms:format',
        //     'type' => 'eq',
        //     'text' => 'application/wkt',
        // ];
        // Added to filter bad formatted annotations.
        $query['resource_class'] = 'oa:Annotation';

        $response = $this->api()->search('annotations', $query);
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $annotations = $response->getContent();
        return new ViewModel([
            'annotations' => $annotations,
            'resources' => $annotations,
        ]);
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
            return $this->jsonError(new PsrMessage(
                'Unauthorized access.', // @translate
            ), Response::STATUS_CODE_403);
        }

        $data = $this->params()->fromPost();

        if (empty($data['wkt'])) {
            return $this->jsonError(new PsrMessage(
                'An internal error occurred from the client: no wkt.', // @translate
            ), Response::STATUS_CODE_400);
        }
        $geometry = $this->checkAndCleanWkt($data['wkt']);
        if (strlen($geometry) == 0) {
            return $this->jsonError(new PsrMessage(
                'An internal error occurred from the client: unmanaged wkt .', // @translate
            ), Response::STATUS_CODE_400);
        }

        $api = $this->viewHelpers()->get('api');

        // Options contains styles and metadata.
        $options = $data['options'] ?? [];
        $metadata = $options['metadata'] ?? [];
        $styles = $options;
        unset($styles['metadata']);
        // Clean old styles and useless leaflet data.
        unset($styles['annotationIdentifier']);
        unset($styles['owner']);
        unset($styles['right']);
        unset($styles['onEachFeature']);
        // Remove bulky icon data (SVG data URIs, HTML, shadow).
        // Only iconColor, iconName and iconSize are kept; the
        // icon is rebuilt on load from these parameters.
        unset($styles['iconUrl']);
        unset($styles['shadowUrl']);
        unset($styles['html']);
        unset($styles['className']);
        if (isset($styles['icon']) && is_array($styles['icon'])) {
            unset($styles['icon']);
        }
        if (empty($styles['_isRectangle'])) {
            unset($styles['_isRectangle']);
        }

        if (empty($data['id'])) {
            if (empty($data['resource_id'])) {
                return $this->jsonError(new PsrMessage(
                    'An internal error occurred from the client: no resource.', // @translate
                ), Response::STATUS_CODE_400);
            }

            $resourceId = $data['resource_id'];
            try {
                $resource = $api->read('resources', ['id' => $resourceId])->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                return $this->jsonError(new PsrMessage(
                    'Resource not found.', // @translate
                ), Response::STATUS_CODE_404);
            }

            // Save media id too to manage multiple media by image, else wms.
            $mediaId = empty($data['media_id']) ? null : $data['media_id'];
            if ($mediaId) {
                try {
                    $media = $api
                        ->read('media', $mediaId)
                        ->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return $this->jsonError(new PsrMessage(
                        'Media #{media_id} not found.', // @translate
                        ['media_id' => $mediaId]
                    ), Response::STATUS_CODE_404);
                }
            } else {
                $media = null;
            }
            return $this->createAnnotation($resource, $geometry, $metadata, $styles, $media);
        }

        try {
            $annotation = $api
                ->read('annotations', $data['id'])
                ->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->jsonError(new PsrMessage(
                'Annotation not found.', // @translate
            ), Response::STATUS_CODE_404);
        }

        return $this->updateAnnotation($annotation, $geometry, $metadata, $styles);
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
            return $this->jsonError(new PsrMessage(
                'Unauthorized access.', // @translate
            ), Response::STATUS_CODE_403);
        }

        $data = $this->params()->fromPost();
        if (empty($data['id'])) {
            return $this->jsonError(new PsrMessage(
                'An internal error occurred from the client: resource id not set.', // @translate
            ), Response::STATUS_CODE_400);
        }

        $id = $data['id'];

        $api = $this->viewHelpers()->get('api');
        try {
            $resource = $api
                ->read('annotations', $id)
                ->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->jsonError(new PsrMessage(
                'Resource not found.', // @translate
            ), Response::STATUS_CODE_404);
        }

        if (!$resource->userIsAllowed('delete')) {
            return $this->jsonError(new PsrMessage(
                'Unauthorized access.', // @translate
            ), Response::STATUS_CODE_403);
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
     * @param array $metadata
     * @param array $styles
     * @param MediaRepresentation|null $media
     * @return \Laminas\View\Model\JsonModel
     */
    protected function createAnnotation(
        AbstractResourceEntityRepresentation $resource,
        $geometry,
        array $metadata,
        array $styles,
        ?MediaRepresentation $media = null
    ) {
        $data = $this->prepareAnnotation($resource, $geometry, $metadata, $styles, $media, null);
        if (!is_array($data)) {
            return $this->jsonError($data, Response::STATUS_CODE_500);
        }

        $response = $this->api()->create('annotations', $data);
        if (!$response) {
            return $this->jsonError(new PsrMessage(
                'An internal error occurred.', // @translate
            ), Response::STATUS_CODE_500);
        }

        $annotation = $response->getContent();

        return new JsonModel([
            'status' => 'success',
            'result' => [
                'id' => $annotation->id(),
                'resourceId' => $resource->id(),
                'annotation' => $annotation->getJsonLd(),
            ],
        ]);
    }

    /**
     * Update a cartographic annotation with geometry.
     *
     * @param AnnotationRepresentation $annotation
     * @param string $geometry
     * @param array $metadata
     * @param array $styles
     * @return \Laminas\View\Model\JsonModel
     */
    protected function updateAnnotation(
        AnnotationRepresentation $annotation,
        $geometry,
        array $metadata,
        array $styles
    ) {
        // TODO Only one target is managed currently.
        $target = $annotation->primaryTarget();
        $resource = $target->sources()[0];

        // The media is saved as selector of the main item (a page in a book).
        // TODO Manage other resource types.
        $mediaValue = $target->value('oa:hasSelector', ['type' => 'resource']);
        $media = $mediaValue && $mediaValue->valueResource()->resourceName() === 'media'
            ? $mediaValue->valueResource()
            : null;

        // For simplicity, the annotation is fully rewritten.
        $data = $this->prepareAnnotation($resource, $geometry, $metadata, $styles, $media, $annotation);
        if (!is_array($data)) {
            return $this->jsonError($data, Response::STATUS_CODE_500);
        }

        $response = $this->api()->update('annotations', $annotation->id(), $data);
        if (!$response) {
            return $this->jsonError(new PsrMessage(
                'An internal error occurred.', // @translate
            ), Response::STATUS_CODE_500);
        }

        return new JsonModel([
            'status' => 'success',
            'result' => [
                'id' => $annotation->id(),
                'resourceId' => $resource->id(),
                'annotation' => $response->getContent()->getJsonLd(),
            ],
        ]);
    }

    /**
     * Prepare the base annotation data from a form.
     *
     * @todo Move most of this checks inside module Annotate.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param string $geometry
     * @param array $metadata
     * @param array $styles
     * @param MediaRepresentation|null $media
     * @param AnnotationRepresentation|null $annotation
     * @return array|string Data or a message of error.
     */
    protected function prepareAnnotation(
        AbstractResourceEntityRepresentation $resource,
        $geometry,
        array $metadata,
        array $styles,
        ?MediaRepresentation $media = null,
        ?AnnotationRepresentation $annotation = null
    ) {
        $api = $this->api();

        // Base annotation (force Annotation resource class).
        $data = [];
        // Force annotation to be public by default, like all resources.
        $data['o:is_public'] = isset($metadata['o:is_public']) && $metadata['o:is_public'] !== null
            ? $metadata['o:is_public']
            : true;
        $data['o:resource_class'] = [
            'o:id' => $this->easyMeta()
                ->resourceClassId('oa:Annotation'),
        ];

        // Check if the template is managed.
        $isDescribe = !empty($media);

        // A template is required for an update (or when there are metadata).
        // Check of the metadata is done below.
        $hasMetadata = $this->hasMetadata($metadata);
        $templateId = $this->forceTemplate($metadata, $hasMetadata, $isDescribe);
        if (empty($templateId) && $hasMetadata) {
            return new PsrMessage(
                'A template is required when there are metadata in an annotation.' // @template
            );
        }

        // Normally, there is no resource template during creation, since it is
        // set in style editor.
        $data['o:resource_template'] = $templateId ? ['o:id' => $templateId] : null;
        $data['oa:hasBody'] = [];
        $data['oa:hasTarget'] = [];

        // Manage target first, because the metadata filled from the template
        // are appended to the required first target automatically.

        // Target.
        $target = $this->fillTarget($resource, $geometry, $styles, $media);
        $target['oa:Annotation'] = $annotation ? ['o:id' => $annotation->id()] : null;
        $data['oa:hasTarget'][] = $target;
        if (!empty($data['oa:hasTarget'][0]['oa:styleClass'])) {
            $data['oa:styledBy'] = [[
                'property_id' => $this->propertyId('oa:styledBy'),
                'type' => 'literal',
                '@value' => json_encode(['leaflet-interactive' => $styles], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]];
        }

        // Body.
        // No template means that only the data of the style editor are available.
        $result = $this->fillDataForTemplate($resource, $metadata, $templateId, $data, $annotation);
        if (!is_array($result)) {
            return $result;
        }

        // Return result anyway.
        $data = $result;
        return $data;
    }

    /**
     * A template is required when there are metadata to define the annotation
     * part of each property (except the non-ubiquitous standard ones).
     *
     * @param array $metadata
     * @param bool $hasMetadata
     * @param bool $isDescribe
     * @return int|null
     */
    protected function forceTemplate(array $metadata, $hasMetadata, $isDescribe)
    {
        $templateId = empty($metadata['o:resource_template']) ? null : (int) $metadata['o:resource_template'];
        $templates = $isDescribe
            ? $this->settings()->get('cartography_template_describe', [])
            : $this->settings()->get('cartography_template_locate', []);
        if ($templateId && !in_array($templateId, $templates)) {
            $templateId = null;
        }
        if ($templateId) {
            return $templateId;
        }

        // A template is required only when there are metadata.
        if (!$hasMetadata) {
            return null;
        }

        if (empty($templates)) {
            return null;
        }

        if (count($templates) === 1) {
            return reset($templates);
        }

        $emptyDefault = $isDescribe
            ? $this->settings()->get('cartography_template_describe_empty')
            : $this->settings()->get('cartography_template_locate_empty');

        if ($emptyDefault) {
            return null;
        }

        return reset($templates);
    }

    /**
     * Check if a metadata received from form has metadata.
     *
     * @todo Improve the check of the existence of metadata according to the template.
     *
     * @param array $metadata
     * @return bool
     */
    protected function hasMetadata(array $metadata)
    {
        return (bool) array_filter($metadata, function ($v, $k) {
            return
                // Remove generic keys and empty values.
                substr($k, 0, 2) !== 'o:'
                && !in_array($k, ['csrf', 'oa:selector', 'oa:hasTarget', 'oa:hasSource', 'oa:styledBy', 'oa:styleClass', 'oa:Annotation'])
                && is_array($v)
                && count($v) !== 0
                // Remove true empty values (0 character or empty array).
                && array_filter($v, function ($w) {
                    return is_array($w) ? !empty($w) : strlen(trim($w)) !== 0;
                })
            ;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Complete resource data with simplified metadata from a form and template.
     *
     * Practically, it creates one or more annotation bodies.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $metadata The simplified metadata
     * @param int|null $templateId
     * @param array $data The normalized metadata
     * @return array|string Data or a message of error.
     */
    protected function fillDataForTemplate(
        AbstractResourceEntityRepresentation $resource,
        array $metadata,
        $templateId,
        array $data,
        ?AnnotationRepresentation $annotation = null
    ) {
        // Note: there is no resource template when created, but it's an error
        // for update (except when there are no metadata, though).
        if (empty($templateId)) {
            return $data;
        }
        $short = $this->shortResourceTemplate($templateId);
        if (empty($short)) {
            return new PsrMessage(
                'Resource template #{resource_template_id} has an issue. Fix settings of Cartography.', // @template
                ['resource_template_id' => $templateId]
            );
        }
        $shortProperties = $short['o:resource_template_property'];

        // List the property terms one time (they are not available in the full
        // template, neither as keys).
        $terms = array_map(function ($v) {
            return $v['o:term'];
        }, $shortProperties);

        /** @var \Omeka\Api\Representation\ResourceTemplateRepresentation $template */
        $template = $this->api()->read('resource_templates', ['id' => $templateId])->getContent();
        $template = json_decode(json_encode($template), true);
        $templateProperties = $template['o:resource_template_property'];

        // Get the special annotation mapping of this template.
        $partValues = [];
        $rtData = $this->settings()
            ->get('annotate_resource_template_data', []);
        $annotationPartMap = $rtData[$templateId] ?? [];

        // Fill the annotation body with the annotation id if any.
        $data['oa:hasBody'] = [[
            'oa:Annotation' => $annotation ? ['o:id' => $annotation->id()] : null,
        ]];

        // Manage the special case for linking (one body for each link), and
        // term must be "oa:hasBody".
        $oaLinking = [];

        foreach ($metadata as $term => $properties) {
            // Skip Omeka data if any.
            if (strpos($term, 'o:') === 0 || !is_array($properties)) {
                continue;
            }

            // Security check: data should be in the resource template.
            // Anyway, don’t process metadata that are not inside the template.
            if (!in_array($term, $terms)) {
                continue;
            }

            // Note: the short key is not working in the front end, so use the
            // one of the short template.
            $shortKey = array_search($term, $terms);
            $propertyId = $templateProperties[$shortKey]['o:property']['o:id'];
            $dataType = $templateProperties[$shortKey]['o:data_type'];
            // Since version 3, it's always an array anyway.
            if (is_array($dataType)) {
                $dataType = reset($dataType);
            }
            $dataType = $dataType ?: 'literal';
            $lang = empty($templateProperties[$shortKey]['o:lang'])
                ? null
                : $templateProperties[$shortKey]['o:lang'];
            foreach ($properties as $value) {
                switch ($dataType) {
                    // TODO Currently, only item is managed for resource template link field.
                    // The short resource template for it to "oa:hasBody".
                    case 'resource':
                    case 'resource:item':
                    case 'resource:itemset':
                    case 'resource:media':
                        // Currently, the resource is managed differently: it
                        // can manage the multiple values…
                        // This value can manage sub values as well (quick add).
                        // TODO Fix/improve front-end to manage links.
                        if ($value && isset($value['value_resource_id'])) {
                            $value = [$value];
                        }
                        foreach ($value as $v) {
                            $oaLinking[] = [
                                'property_id' => $propertyId,
                                'type' => $dataType,
                                'value_resource_id' => $v['value_resource_id'],
                                '@value' => null,
                                '@lang' => null,
                            ];
                        }
                        break;
                    case 'uri':
                    case strlen($dataType) && strpos($dataType, 'valuesuggest:') === 0:
                        $partValues[$term][] = [
                            'property_id' => $propertyId,
                            'type' => $dataType,
                            '@id' => $value,
                            'o:label' => null,
                            '@value' => null,
                            '@lang' => null,
                        ];
                        break;
                    case 'literal':
                    case strlen($dataType) && strpos($dataType, 'customvocab:') === 0:
                    default:
                        $partValues[$term][] = [
                            'property_id' => $propertyId,
                            'type' => $dataType,
                            '@value' => $value,
                            '@lang' => $lang,
                        ];
                        break;
                }
            }
        }

        foreach ($partValues as $term => $pValues) {
            foreach ($pValues as $partValue) {
                $annotationPart = $annotationPartMap[$term] ?? 'oa:Annotation';
                if ($annotationPart === 'oa:Annotation') {
                    $data[$term][] = $partValue;
                } else {
                    $data[$annotationPart][0][$term][] = $partValue;
                }
            }
        }

        // TODO Move exception inside adapter. Or force resource template?
        // Manage exceptions to follow the Annotation data model.

        // Remove the purpose if there is no text description.
        if (empty($data['oa:hasBody'][0]['rdf:value'])
            && !empty($data['oa:hasBody'][0]['oa:hasPurpose'])
        ) {
            unset($data['oa:hasBody'][0]['oa:hasPurpose']);
        }

        // Manage the special case for annotation resource link: each link is a
        // separate body. Deduplicate ids too.
        if ($oaLinking) {
            $ids = [];
            $annotationId = $annotation ? ['o:id' => $annotation->id()] : null;
            foreach ($oaLinking as $valueResource) {
                $id = $valueResource['value_resource_id'];
                // Skip duplicates.
                if (in_array($id, $ids)) {
                    continue;
                }
                // TODO Replace rdf:value by oa:hasBody but allow associated metadata for links.
                $ids[] = $id;
                $oaLinkingValues = [];
                $oaLinkingValues['oa:Annotation'] = $annotationId;
                $oaLinkingValues['rdf:value'][] = [
                    'property_id' => $this->propertyId('rdf:value'),
                    'type' => 'resource',
                    'value_resource_id' => $id,
                ];
                $data['oa:hasBody'][] = $oaLinkingValues;
            }
        }

        // Remove empty bodies.
        foreach ($data['oa:hasBody'] as $key => $oaHasBody) {
            unset($oaHasBody['oa:Annotation']);
            if (empty($oaHasBody)) {
                unset($data['oa:hasBody'][$key]);
            }
        }

        return $data;
    }

    /**
     * Create a target with simplified metadata from the form.
     *
     * Only one target is managed. Five default values are set.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param string $geometry The wkt.
     * @param array $styles The styles of the annotation.
     * @param MediaRepresentation $media
     * @return array
     */
    protected function fillTarget(
        AbstractResourceEntityRepresentation $resource,
        $geometry,
        array $styles = [],
        ?MediaRepresentation $media = null
    ) {
        $target = [];
        $target['oa:hasSource'] = [[
            'property_id' => $this->propertyId('oa:hasSource'),
            'type' => 'resource',
            'value_resource_id' => $resource->id(),
        ]];
        if ($media) {
            $target['oa:hasSelector'] = [[
                'property_id' => $this->propertyId('oa:hasSelector'),
                'type' => 'resource',
                'value_resource_id' => $media->id(),
            ]];
        }
        // Currently, selectors are managed as a type internally.
        $target['rdf:type'] = [[
            'property_id' => $this->propertyId('rdf:type'),
            'type' => 'customvocab:' . $this->customVocabId('Annotation Target rdf:type'),
            // TODO Or oa:WKTSelector when it will be extended? oa:Selector or Selector?
            '@value' => 'oa:Selector',
        ]];
        $target['dcterms:format'] = [[
            'property_id' => $this->propertyId('dcterms:format'),
            'type' => 'customvocab:' . $this->customVocabId('Annotation Target dcterms:format'),
            '@value' => 'application/wkt',
        ]];
        $target['rdf:value'] = [[
            'property_id' => $this->propertyId('rdf:value'),
            'type' => empty($media) ? 'geography' : 'geometry',
            '@value' => $geometry,
        ]];
        // There is no style during creation.
        $target['oa:styleClass'] = [];
        if (array_key_exists('_isRectangle', $styles) && !$styles['_isRectangle']) {
            unset($styles['_isRectangle']);
        }
        if (count($styles)) {
            $target['oa:styleClass'] = [[
                'property_id' => $this->propertyId('oa:styleClass'),
                'type' => 'literal',
                '@value' => 'leaflet-interactive',
            ]];
        }

        return $target;
    }

    /**
     * Convert a standard resource template into a js manageable one.
     *
     * @param int $templateId
     * @return array|null Return null if the resource template doesn't exist or
     * in case of error.
     */
    protected function shortResourceTemplate($templateId)
    {
        try {
            /** @var \Omeka\Api\Representation\ResourceTemplateRepresentation $template */
            $template = $this->api()->read('resource_templates', ['id' => $templateId])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $this->logger()->err(
                'Resource template #{template_id} doesn’t exist any more. Fix settings of Cartography.', // @template
                ['template_id' => $templateId]
            );
            return null;
        }

        $short = [];

        // TODO The js cannot managed the same field with multiple types currently (issue for resource link).
        $check = [];

        $short['o:id'] = $template->id();
        $short['o:label'] = $template->label();
        $short['o:resource_template_property'] = [];
        foreach ($template->resourceTemplateProperties() as $templateProperty) {
            // The data type may have been removed (custom vocab, etc.), so a
            // default is forced.
            $dataType = $templateProperty->dataTypes();
            $dataType = reset($dataType) ?: 'literal';
            $input = [];
            $input['o:id'] = $templateProperty->property()->id();
            $input['o:term'] = $templateProperty->property()->term();
            $input['o:label'] = $templateProperty->alternateLabel() ?: $templateProperty->property()->label();
            $input['o:comment'] = $templateProperty->alternateComment() ?: $templateProperty->property()->comment();
            $input['o:data_type'] = $dataType;

            // Manage an exception for oa:hasBody, that must be a resource and
            // vice-versa below.
            if ($input['o:term'] === 'oa:hasBody'
                && !in_array($input['o:data_type'], ['resource', 'resource:item', 'resource:itemset', 'resource:media'])
            ) {
                $this->logger()->warn(
                    'To follow the annotation data model and for technical reasons, "oa:hasBody" must be a resource link to be managed internally. Check your resource template "{resource_template}".', // @template
                    ['resource_template' => $template->label()]
                );
                return null;
            }
            if ($input['o:term'] !== 'oa:hasBody'
                && in_array($input['o:data_type'], ['resource', 'resource:item', 'resource:itemset', 'resource:media'])
            ) {
                $this->logger()->warn(
                    'To follow the annotation data model and for technical reasons, the resource links must use the property "oa:hasBody" to be managed internally. Check your resource template "{resource_template}".', // @template
                    ['resource_template' => $template->label()]
                );
                return null;
            }

            // Don't skip missing datatype in order to keep the same order than
            // the original template.
            switch ($dataType) {
                // TODO Currently, only resource:item is managed for resource template link field.
                case 'resource':
                case 'resource:item':
                    $input['type'] = 'resource';
                    break;
                case 'resource:itemset':
                case 'resource:media':
                    $this->logger()->warn(
                        'Resource link "{data_type}" is currently not managed for resource template "{resource_template}": it should be a "resource" or a "resource:item".', // @template
                        ['data_type' => $dataType, 'resource_template' => $template->label()]
                    );
                    return null;
                case 'uri':
                    $input['type'] = 'uri';
                    break;
                case strlen($dataType) && strpos($dataType, 'customvocab:') === 0:
                    $customVocabId = (int) substr($dataType, 12);
                    try {
                        /** @var \CustomVocab\Api\Representation\CustomVocabRepresentation $customVocab */
                        $customVocab = $this->api()->read('custom_vocabs', ['id' => $customVocabId])->getContent();
                    } catch (\Omeka\Api\Exception\NotFoundException $e) {
                        $this->logger()->warn(
                            'Custom vocab #{custom_vocab_id} doesn’t exist any more. Fix resource template "{resource_template}".', // @template
                            ['custom_vocab_id' => $customVocabId, 'resource_template' => $template->label()]
                        );
                        $input['type'] = 'text';
                        break;
                    }
                    $terms = $customVocab->terms();
                    if ($terms && !is_array($terms)) {
                        $terms = array_unique(array_filter(array_map('trim', explode("\n", $terms))));
                    }
                    if (empty($terms)) {
                        $this->logger()->warn(
                            'Custom vocab "{custom_vocab}" doesn’t have terms.', // @template
                            ['custom_vocab' => $customVocab->label()]
                        );
                        $input['type'] = 'text';
                        break;
                    }
                    $input['type'] = 'select';
                    $input['value_options'] = array_combine($terms, $terms);
                    break;
                case strlen($dataType) && strpos($dataType, 'valuesuggest:') === 0:
                    $input['type'] = 'valuesuggest';
                    $input['valuesuggest']['service_url'] = $this->url()
                        // TODO Only admin currently: prepare public.
                        ->fromRoute('admin/value-suggest/proxy', [], ['query' => ['type' => $dataType]], true);
                    break;
                case 'literal':
                default:
                    $input['type'] = 'textarea';
                    break;
            }

            // TODO Any short form should be possible (except for linking). Check if the check is still needed.
            if (isset($check[$input['o:term']]) && $input['type'] !== $check[$input['o:term']]) {
                $this->logger()->warn(
                    'Short resource template doesn’t support different data types for the same property. Check resource template "{resource_template}".', // @template
                    ['resource_template' => $template->label()]
                );
                return null;
            }

            $input['o:is_required'] = $templateProperty->isRequired();
            $short['o:resource_template_property'][] = $input;
            $check[$input['o:term']] = $input['type'];
        }

        return $short;
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

        $imageType = $params['type'] ?? null;
        foreach ($medias as $media) {
            $image = [];
            $image['id'] = $media->id();

            // Check for IIIF media (no original file).
            if (!$media->hasOriginal()) {
                $mediaData = $media->mediaData();
                if (!is_array($mediaData)) {
                    continue;
                }

                $ingester = $media->ingester();

                // IIIF Image: single image with info.json.
                if ($ingester === 'iiif'
                    && !empty($mediaData['width'])
                    && !empty($mediaData['height'])
                ) {
                    $iiifId = $mediaData['@id']
                        ?? $mediaData['id']
                        ?? null;
                    if ($iiifId) {
                        $image['iiif'] = rtrim($iiifId, '/')
                            . '/info.json';
                        $image['size'] = [
                            (int) $mediaData['width'],
                            (int) $mediaData['height'],
                        ];
                        $image['label'] = $media->displayTitle();
                        $images[] = $image;
                    }
                }

                // IIIF Presentation: extract each canvas.
                elseif ($ingester === 'iiif_presentation') {
                    $manifestLabel = $this->iiifLabel(
                        $mediaData['label'] ?? null
                    ) ?: $media->displayTitle();
                    $canvases = $this->extractCanvases(
                        $mediaData
                    );
                    $total = count($canvases);
                    foreach ($canvases as $i => $canvas) {
                        $canvasImage = [];
                        $canvasImage['id'] = $media->id();
                        $canvasImage['canvasIndex'] = $i;
                        $canvasImage['label'] = $total > 1
                            ? $manifestLabel . ' ['
                                . ($i + 1) . '/' . $total . ']'
                            : $manifestLabel;
                        $canvasImage['iiif'] = $canvas['iiif'];
                        $canvasImage['size'] = $canvas['size'];
                        $images[] = $canvasImage;
                    }
                }

                continue;
            }

            $size = $this->imageSize($media, $imageType);
            if (empty($size['width'])) {
                continue;
            }
            $image['url'] = $media->originalUrl();
            $image['size'] = array_values($size);
            $image['label'] = $media->displayTitle();
            $images[] = $image;
        }

        return $images;
    }

    /**
     * Extract a simple string label from IIIF label data.
     *
     * IIIF v3 labels are objects like {"none":["text"]}.
     */
    protected function iiifLabel($label): string
    {
        if (is_string($label)) {
            return $label;
        }
        if (is_array($label)) {
            $values = reset($label);
            if (is_array($values)) {
                return (string) reset($values);
            }
            return (string) $values;
        }
        return '';
    }

    /**
     * Extract image data from IIIF Presentation canvases.
     *
     * Supports both IIIF Presentation v2 (sequences/canvases)
     * and v3 (items).
     *
     * @return array Each entry has label, iiif, size.
     */
    protected function extractCanvases(array $manifest): array
    {
        $result = [];

        // v2: sequences[0].canvases, v3: items.
        $canvases = [];
        if (!empty($manifest['sequences'])) {
            foreach ($manifest['sequences'] as $seq) {
                $canvases = array_merge(
                    $canvases,
                    $seq['canvases'] ?? []
                );
            }
        } elseif (!empty($manifest['items'])) {
            $canvases = $manifest['items'];
        }

        foreach ($canvases as $i => $canvas) {
            $width = $canvas['width'] ?? null;
            $height = $canvas['height'] ?? null;
            if (!$width || !$height) {
                continue;
            }

            // Extract the IIIF Image service from the canvas.
            $serviceId = $this->extractImageService($canvas);
            if (!$serviceId) {
                continue;
            }

            // Extract label.
            $label = $canvas['label'] ?? null;
            if (is_array($label)) {
                $label = reset($label);
                if (is_array($label)) {
                    $label = reset($label);
                }
            }
            $label = $label ?: ('Canvas ' . ($i + 1));

            $result[] = [
                'label' => $label,
                'iiif' => rtrim($serviceId, '/')
                    . '/info.json',
                'size' => [(int) $width, (int) $height],
            ];
        }

        return $result;
    }

    /**
     * Extract the IIIF Image service ID from a canvas.
     *
     * Traverses annotation pages and annotations to find a
     * painting annotation with an image body that has a
     * IIIF Image service.
     */
    protected function extractImageService(array $canvas): ?string
    {
        // v2: canvas.images[].resource.service
        // v3: canvas.items[].items[].body.service
        $annotations = [];
        if (!empty($canvas['images'])) {
            $annotations = $canvas['images'];
        } elseif (!empty($canvas['items'])) {
            foreach ($canvas['items'] as $page) {
                if (($page['type'] ?? '') === 'AnnotationPage'
                    && !empty($page['items'])
                ) {
                    $annotations = array_merge(
                        $annotations,
                        $page['items']
                    );
                }
            }
        }

        foreach ($annotations as $annotation) {
            // v2: resource, v3: body.
            $body = $annotation['body']
                ?? $annotation['resource']
                ?? null;
            if (!$body) {
                continue;
            }
            $type = $body['type'] ?? $body['@type'] ?? '';
            if (stripos($type, 'Image') === false
                && ($body['format'] ?? '') !== 'image/jpeg'
            ) {
                continue;
            }
            // Get service.
            $service = $body['service'] ?? null;
            if (!$service) {
                continue;
            }
            if (is_array($service)
                && isset($service[0])
            ) {
                $service = $service[0];
            }
            $serviceId = $service['@id']
                ?? $service['id']
                ?? null;
            if ($serviceId) {
                return $serviceId;
            }
        }

        return null;
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
        $values = $resource->value('dcterms:spatial', ['type' => 'uri', 'all' => true]);
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
     * - annotation_id: to specify an annotation, else all annotations are
     * returned.
     * @return array Array of geometries.
     */
    protected function fetchSimpleGeometries(?AbstractResourceEntityRepresentation $resource, array $query = [])
    {
        if ($resource) {
            $query['resource_id'] = $resource->id();
        }

        $geometries = [];

        $mediaId = array_key_exists('mediaId', $query)
            ? (int) $query['mediaId']
            : (array_key_exists('media_id', $query)
                ? (int) $query['media_id']
                : null);
        if ($mediaId) {
            $geometryTypes = ['geometry'];
        } elseif ($mediaId === 0) {
            $geometryTypes = ['geography'];
        } else {
            $geometryTypes = ['geometry', 'geography'];
        }

        // The search is done via the annotation adapter, not the resource one.
        if (array_key_exists('annotation_id', $query)) {
            if (!empty($query['annotation_id'])) {
                $query['id'] = $query['annotation_id'];
            }
            unset($query['annotation_id']);
        }

        /** @var \Annotate\Api\Representation\AnnotationRepresentation[] $annotations */
        $annotations = $this->api()->search('annotations', $query)->getContent();
        foreach ($annotations as $annotation) {
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

            // An annotation has only one wkt, that can be a geometry or a
            // geography.
            foreach ($geometryTypes as $geometryType) {
                $value = $target->value('rdf:value', ['all' => false, 'type' => $geometryType, 'default' => []]);
                if ($value) {
                    // Remove the srid if any.
                    $value = $value->value();
                    $geometry['wkt'] = stripos($value, 'srid') !== false
                        ? trim(substr($value, strpos($value, ';') + 1))
                        : $value;
                    break;
                }
            }
            if (empty($geometry['wkt'])) {
                continue;
            }

            // TODO Manage other resource types.
            $hasSelector = $target->value('oa:hasSelector', ['type' => 'resource']);
            if ($hasSelector) {
                $geometry['mediaId'] = $hasSelector->valueResource()->id();
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

            // Simplify the metadata.
            // All properties are mixed, they will be separated automatically
            // according to the resource template or via the annotation process.
            $metadata = [];
            $metadata['o:id'] = $annotation->id();
            $metadata['o:is_public'] = $annotation->isPublic();
            $owner = $annotation->owner();
            $metadata['o:owner'] = [];
            $metadata['o:owner']['id'] = $owner->id();
            $metadata['o:owner']['name'] = $owner->name();
            $metadata['o:created'] = $annotation->created()->format('Y-m-d H:i:s');
            $metadata['o:modified'] = $annotation->modified()->format('Y-m-d H:i:s');
            // TODO Possibly return only the metadata of the template.
            $resourceTemplate = $annotation->resourceTemplate();
            $metadata['o:resource_template'] = $resourceTemplate ? $resourceTemplate->id() : null;

            // Properties of the annotation.
            $specialProperties = [
                'oa:styledBy' => true,
                'oa:hasBody' => true,
                'oa:hasTarget' => true,
            ];
            $metadata = $this->appendProperties($annotation, $metadata, $specialProperties);

            // Properties of bodies.
            $specialProperties = [
                // Manage the special case of the body resources: they are
                // managed as rdf:value resource internally, but oa:hasBody
                // in the front-end.
                'rdf:value' => ['resource' => 'oa:hasBody'],
            ];
            foreach ($annotation->bodies() as $annotationBody) {
                $metadata = $this->appendProperties($annotationBody, $metadata, $specialProperties);
            }

            // Properties of targets.
            // Generally, nothing is needed in target: it is the geometry itself
            // (source, wkt, style class…) but there may be optional properties.
            $specialProperties = [
                'oa:hasSource' => true,
                'rdf:type' => true,
                'dcterms:format' => true,
                'rdf:value' => true,
                'oa:styleClass' => true,
                // Forbid it only for locate.
                // 'oa:hasSelector' => true,
            ];
            foreach ($annotation->targets() as $annotationTarget) {
                $metadata = $this->appendProperties($annotationTarget, $metadata, $specialProperties);
            }

            $geometry['options']['metadata'] = $metadata;

            $geometry['options']['right'] = [
                'edit' => $annotation->userIsAllowed('update'),
                'delete' => $annotation->userIsAllowed('delete'),
            ];

            $geometries[] = $geometry;
        }

        return $geometries;
    }

    /**
     * Simplify properties of a resource.
     *
     * @param AbstractResourceEntityRepresentation $annotation
     * @param array $metadata
     * @param array $specialProperties
     * @return array
     */
    protected function appendProperties(
        $resource,
        array $metadata,
        array $specialProperties = []
    ) {
        foreach ($resource->values() as $term => $property) {
            if (isset($specialProperties[$term])
                && is_bool($specialProperties[$term])
            ) {
                continue;
            }
            // AnnotationPartValues::values() returns
            // [term => [ValueRep...]], while
            // AbstractResourceEntityRepresentation::values()
            // returns [term => ['values' => [ValueRep...]]].
            $values = isset($property['values'])
                ? $property['values']
                : (is_array($property) ? $property : []);
            /** @var \Omeka\Api\Representation\ValueRepresentation $value */
            foreach ($values as $value) {
                switch ($value->type()) {
                case 'resource':
                    if (isset($specialProperties[$term]['resource'])) {
                        $term = $specialProperties[$term]['resource'];
                    }
                    $metadata[$term][] = $value->valueResource()->valueRepresentation();
                    break;
                case 'uri':
                    $metadata[$term][] = ['value' => $value->value(), 'uri' => $value->uri()];
                    break;
                case strpos($value->type(), 'valuesuggest:') === 0:
                    $metadata[$term][] = ['value' => $value->value(), 'uri' => $value->uri()];
                    break;
                case strpos($value->type(), 'customvocab:') === 0:
                    $metadata[$term][] = $value->value();
                    break;
                case 'literal':
                default:
                    $metadata[$term][] = $value->value();
                    break;
            }
            }
        }
        return $metadata;
    }

    protected function propertyId($term)
    {
        return $this->easyMeta()->propertyId($term);
    }

    protected function customVocabId($label)
    {
        $api = $this->viewHelpers()->get('api');
        $result = $api->read('custom_vocabs', ['label' => $label])->getContent();
        return $result ? $result->id() : null;
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
        $string = trim((string) $string);
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
        if ($message instanceof PsrMessage) {
            $message->setTranslator($this->translator());
        }
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        return new JsonModel([
            'status' => 'error',
            'message' => $message,
        ]);
    }

    abstract protected function notAjax();
}
