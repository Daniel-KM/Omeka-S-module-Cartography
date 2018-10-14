<?php
namespace Cartography\Controller\Admin;

use Annotate\Api\Representation\AnnotationRepresentation;
use Annotate\Entity\Annotation;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

class CartographyController extends AbstractActionController
{
    /**
     * Get the geometries for a resource.
     *
     * @return JsonModel
     */
    public function geometriesAction()
    {
        $id = $this->params('id');
        if (!$id) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'Not found.',
            ]);
        }
        $resource = $this->api()->read('resources', $id)->getContent();
        if (!$resource) {
            return new JsonModel([
                'status' => 'error',
                'message' => 'Not found.',
            ]);
        }

        $query = $this->params()->fromQuery();
        $geometries = $this->fetchGeometries($resource, $query);

        return new JsonModel([
            'status' => 'success',
            'resourceId' => $id,
            'geometries' => $geometries,
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
            $this->messenger()->addError('This url is not available.'); // @translate
            $urlHelper = $this->viewHelpers()->get('url');
            return $this->redirect()->toUrl($urlHelper('admin'));
        }

        $isPost = $this->getRequest()->isPost();
        if (!$isPost) {
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        $data = $this->params()->fromPost();
        if (empty($data['wkt'])) {
            return $this->jsonError('An internal error occurred from the client.', Response::STATUS_CODE_400); // @translate
        }
        $geometry = $this->checkAndCleanWkt($data['wkt']);
        if (strlen($geometry) == 0) {
            return $this->jsonError('An internal error occurred from the client.', Response::STATUS_CODE_400); // @translate
        }

        // Options contains styles and description.
        $options = isset($data['options']) ? $data['options'] : [];

        // Check if it is an update.
        if (!empty($data['id'])) {
            $id = $data['id'];
            $api = $this->viewHelpers()->get('api');
            $resource = $api
                ->searchOne('annotations', ['id' => $id])
                ->getContent();
            if (!$resource) {
                return $this->jsonError('Resource not found.', Response::STATUS_CODE_404); // @translate
            }

            // TODO Remove this value, since it cannot change.
            $options['mediaId'] = empty($data['mediaId']) ? null : $data['mediaId'];

            return $this->updateAnnotation($resource, $geometry, $options);
        }

        if (empty($data['resourceId'])) {
            return $this->jsonError('An internal error occurred from the client.', Response::STATUS_CODE_400); // @translate
        }

        // Default motivation for the module Cartography is "highlighting".
        // Note: it can be bypassed by data options.
        $oaMotivatedBy = empty($data['oaMotivatedBy']) ? 'highlighting' : $data['oaMotivatedBy'];

        // Save the media id too to manage multiple media by image.
        $options['mediaId'] = empty($data['mediaId']) ? null : $data['mediaId'];

        $resourceId = $data['resourceId'];
        return $this->createAnnotation($resourceId, $geometry, $options, $oaMotivatedBy);
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
            $this->messenger()->addError('This url is not available.'); // @translate
            $urlHelper = $this->viewHelpers()->get('url');
            return $this->redirect()->toUrl($urlHelper('admin'));
        }

        // TODO Use "Delete" instead of "Post".
        $isPost = $this->getRequest()->isPost();
        if (!$isPost) {
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        $data = $this->params()->fromPost();
        if (empty($data['id'])) {
            return $this->jsonError('An internal error occurred from the client.', Response::STATUS_CODE_400); // @translate
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
     * @param int $resourceId
     * @param string $geometry
     * @param array $options
     * @param string $oaMotivatedBy
     * @return \Zend\View\Model\JsonModel
     */
    protected function createAnnotation($resourceId, $geometry, array $options, $oaMotivatedBy)
    {
        $api = $this->api();

        $data = [
            'o:is_public' => 1,
            'o:resource_template' => ['o:id' => $api->searchOne('resource_templates', ['label' => 'Annotation'])->getContent()->id()],
            'o:resource_class' => ['o:id' => $api->searchOne('resource_classes', ['term' => 'oa:Annotation'])->getContent()->id()],
            'oa:motivatedBy' => [
                [
                    'property_id' => $this->propertyId('oa:motivatedBy'),
                    'type' => 'customvocab:' . $this->customVocabId('Annotation oa:motivatedBy'),
                    '@value' => $oaMotivatedBy,
                ],
            ],
            'o-module-annotate:target' => [
                [
                    'oa:hasSource' => [
                        [
                            'property_id' => $this->propertyId('oa:hasSource'),
                            'type' => 'resource',
                            'value_resource_id' => $resourceId,
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
            // 'rdf:value' => [
            //     [
            //         'property_id' => $this->propertyId('rdf:value'),
            //         'type' => 'literal',
            //         '@value' => $geometry,
            //     ],
            // ],
        ];

        $data['o-module-annotate:target'][0]['rdf:value'][] = [
            'property_id' => $this->propertyId('rdf:value'),
            'type' => 'literal',
            '@value' => $geometry,
        ];

        if ($options) {
            unset($options['annotationIdentifier']);

            if (!empty($options['oaMotivatedBy'])) {
                $data['oa:motivatedBy'] = [
                    [
                        'property_id' => $this->propertyId('oa:motivatedBy'),
                        'type' => 'customvocab:' . $this->customVocabId('Annotation oa:motivatedBy'),
                        '@value' => $options['oaMotivatedBy'],
                    ],
                ];
            }

            if (isset($options['popupContent']) && strlen($options['popupContent'])) {
                $data['o-module-annotate:body'] = [
                    [
                        'rdf:value' => [
                            [
                                'property_id' => $this->propertyId('rdf:value'),
                                'type' => 'literal',
                                '@value' => $options['popupContent'],
                            ],
                        ],
                    ],
                ];

                if (empty($options['oaHasPurpose'])) {
                    $data['o-module-annotate:body'][0]['oa:hasPurpose'] = [];
                } else {
                    $data['o-module-annotate:body'][0]['oa:hasPurpose'] = [
                        [
                            'property_id' => $this->propertyId('oa:hasPurpose'),
                            'type' => 'customvocab:' . $this->customVocabId('Annotation Body oa:hasPurpose'),
                            '@value' => $options['oaHasPurpose'],
                        ],
                    ];
                }
            } else {
                $data['o-module-annotate:body'] = [];
            }

            if (!empty($options['cartographyUncertainty'])) {
                $data['o-module-annotate:target'][0]['cartography:uncertainty'] = [
                    [
                        'property_id' => $this->propertyId('cartography:uncertainty'),
                        'type' => 'customvocab:' . $this->customVocabId('Cartography cartography:uncertainty'),
                        '@value' => $options['cartographyUncertainty'],
                    ],
                ];
            }

            unset($options['oaMotivatedBy']);
            unset($options['popupContent']);
            unset($options['oaHasPurpose']);
            unset($options['cartographyUncertainty']);

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
                'resourceId' => $resourceId,
                'annotation' => $annotation->getJsonLd(),
            ],
        ]);
    }

    /**
     * Update a cartographic annotation with geometry.
     *
     * @param int $resourceId
     * @param string $geometry
     * @param array $options
     * @return \Zend\View\Model\JsonModel
     */
    protected function updateAnnotation(AnnotationRepresentation $annotation, $geometry, array $options)
    {
        $api = $this->api();

        // TODO Only one body, if any, and one target is managed currently.
        $body = $annotation->primaryBody();
        $target = $annotation->primaryTarget();
//         if ($target) {
//             // TODO Fix update of an existing target (or always use api annotations?).
//             $data = [
//                 'rdf:value' => [
//                     [
//                         'property_id' => $this->propertyId('rdf:value'),
//                         'type' => 'literal',
//                         '@value' => $wkt,
//                     ],
//                 ],
//             ];
//             $response = $api->update('annotation_targets', $target->id(), $data, [], ['isPartial' => true]);
//         } else {

            $data = [];

            // The media id is not updatable, but is needed for partial update.
            $hasMediaId = !empty($options['mediaId']);
            if ($hasMediaId) {
                $data['o-module-annotate:target'][0]['rdf:value'][] = [
                    'property_id' => $this->propertyId('rdf:value'),
                    'type' => 'resource',
                    'value_resource_id' => $options['mediaId'],
                ];
                unset($options['mediaId']);
            }

            $data['o-module-annotate:target'][0]['rdf:value'][] = [
                'property_id' => $this->propertyId('rdf:value'),
                'type' => 'literal',
                '@value' => $geometry,
            ];

            // TODO Remove a popup content.

            if ($options) {
                // TODO Check if original and editing are the same to avoid update or to create a useless style.
                unset($options['original']);
                unset($options['editing']);
                unset($options['annotationIdentifier']);

                if (!empty($options['oaMotivatedBy'])) {
                    $data['oa:motivatedBy'] = [
                        [
                            'property_id' => $this->propertyId('oa:motivatedBy'),
                            'type' => 'customvocab:' . $this->customVocabId('Annotation oa:motivatedBy'),
                            '@value' => $options['oaMotivatedBy'],
                        ],
                    ];
                }

                if (isset($options['popupContent']) && strlen($options['popupContent'])) {
                    $data['o-module-annotate:body'] = [
                        [
                            'rdf:value' => [
                                [
                                    'property_id' => $this->propertyId('rdf:value'),
                                    'type' => 'literal',
                                    '@value' => $options['popupContent'],
                                ],
                            ],
                        ],
                    ];

                    if (empty($options['oaHasPurpose'])) {
                        $data['o-module-annotate:body'][0]['oa:hasPurpose'] = [];
                    } else {
                        $data['o-module-annotate:body'][0]['oa:hasPurpose'] = [
                            [
                                'property_id' => $this->propertyId('oa:hasPurpose'),
                                'type' => 'customvocab:' . $this->customVocabId('Annotation Body oa:hasPurpose'),
                                '@value' => $options['oaHasPurpose'],
                            ],
                        ];
                    }
                } else {
                    $data['o-module-annotate:body'] = [];
                }

                if (empty($options['cartographyUncertainty'])) {
                    $data['o-module-annotate:target'][0]['cartography:uncertainty'] = [];
                } else {
                    $data['o-module-annotate:target'][0]['cartography:uncertainty'] = [
                        [
                            'property_id' => $this->propertyId('cartography:uncertainty'),
                            'type' => 'customvocab:' . $this->customVocabId('Cartography cartography:uncertainty'),
                            '@value' => $options['cartographyUncertainty'],
                        ],
                    ];
                }

                unset($options['oaMotivatedBy']);
                unset($options['popupContent']);
                unset($options['oaHasPurpose']);
                unset($options['cartographyUncertainty']);

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

            // Partial update is complex, so reload the full annotation.

            // $response = $api->update('annotations', $annotation->id(), $data, [], ['isPartial' => true]);

            // // Only one target is managed.
            // $response = $api->update('annotation_targets', $target->id(), [
            //     'rdf:value' => $data['o-module-annotate:target'][0]['rdf:value'],
            // ], [], ['isPartial' => true]);
            // if ($options) {
            //     $response = $api->update('annotation_targets', $target->id(), [
            //         'oa:styleClass' => $data['o-module-annotate:target'][0]['oa:styleClass'],
            //     ], [], ['isPartial' => true]);
            //     $response = $api->update('annotations', $annotation->id(), [
            //         'oa:styledBy' => $data['oa:styledBy'],
            //     ], [], ['isPartial' => true]);
            // }

            // Update main annotation first.
            if ($options) {
                $values = $this->arrayValues($annotation);
                $values['oa:styledBy'] = $data['oa:styledBy'];
                if (!empty($data['oa:motivatedBy'])) {
                    $values['oa:motivatedBy'] = $data['oa:motivatedBy'];
                }
                $response = $api->update('annotations', $annotation->id(), $values, [], ['isPartial' => true]);
            }

            // There may be no body.
            $values = $this->arrayValues($body);
            if (isset($data['o-module-annotate:body'][0]['rdf:value'][0]['@value'])) {
                $values['rdf:value'] = $data['o-module-annotate:body'][0]['rdf:value'];
                $values['oa:hasPurpose'] = $data['o-module-annotate:body'][0]['oa:hasPurpose'];
                if ($body) {
                    $response = $api->update('annotation_bodies', $body->id(), $values, [], ['isPartial' => true]);
                } else {
                    $values['o-module-annotate:annotation'] = $annotation;
                    $response = $api->create('annotation_bodies', $values, []);
                }
            } elseif ($body) {
                $response = $api->delete('annotation_bodies', $body->id());
            }

            // There is always one target at least.
            $values = $this->arrayValues($target);
            $values['rdf:value'] = $data['o-module-annotate:target'][0]['rdf:value'];
            if ($options) {
                $values['oa:styleClass'] = $data['o-module-annotate:target'][0]['oa:styleClass'];
                $values['cartography:uncertainty'] = $data['o-module-annotate:target'][0]['cartography:uncertainty'];
            }
            $response = $api->update('annotation_targets', $target->id(), $values, [], ['isPartial' => true]);

//         }
        if (!$response) {
            return $this->jsonError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        return new JsonModel([
            'status' => 'success',
            'result' => true,
        ]);
    }

    /**
     * Prepare all geometries for a resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $query Query to specify the geometries. May have optional
     * argument "mediaId": if integer greater than or equal to 1, get only the
     * geometries for that media; if equal to 0, get only geometries without
     * media id; if equal to -1, get all geometries with a media id; if not set,
     * get all geometries, whatever they have a media id or not.
     * @return array Associative array of geometries by annotation id.
     */
    protected function fetchGeometries(AbstractResourceEntityRepresentation $resource, array $query = [])
    {
        $geometries = [];

        $mediaId = array_key_exists('mediaId', $query)
            ? (int) $query['mediaId']
            : null;

        /** @var \Annotate\Api\Representation\AnnotationRepresentation[] $annotations */
        $annotations = $this->resourceAnnotations($resource, $query);
        foreach ($annotations as $annotation) {
            // Currently, only one target by annotation.
            // Most of the properties of the annotation are on the target.
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

            $value = $annotation->value('oa:motivatedBy');
            $geometry['options']['oaMotivatedBy'] = $value ? $value->value() : '';

            // Links (they don't have textual description).
            if ($geometry['options']['oaMotivatedBy'] === 'linking') {
                $values = $annotation->value('oa:hasBody', ['type' => 'resource', 'all' => true, 'default' => []]);
                foreach ($values as $value) {
                    /** @var \Omeka\Api\Representation\ItemRepresentation $valueResource */
                    $valueResource = $value->valueResource();
                    $geometry['options']['oaLinking'][] = [
                        'id' => $valueResource->id(),
                        'title' => $valueResource->displayTitle(),
                        // TODO Use linkPretty() (and use it in js).
                        'url' => $valueResource->adminUrl(),
                    ];
                }
            }
            // Textual bodies.
            // TODO There may be multiple bodies.
            else {
                $body = $annotation->primaryBody();
                if ($body) {
                    $value = $body->value('rdf:value');
                    $geometry['options']['popupContent'] = $value ? $value->value() : '';
                    $value = $body->value('oa:hasPurpose');
                    $geometry['options']['oaHasPurpose'] = $value ? $value->value() : '';
                }
            }

            $value = $target->value('cartography:uncertainty');
            $geometry['options']['cartographyUncertainty'] = $value ? $value->value() : '';

            $geometries[$annotation->id()] = $geometry;
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
                        $arrayValue['o:label'] = $value->value();
                        break;
                    case 'resource':
                        $arrayValue['value_resource_id'] = $value->valueResource()->id();
                        break;
                    case 'literal':
                    default:
                        $arrayValue['language'] = $value->lang();
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
}
