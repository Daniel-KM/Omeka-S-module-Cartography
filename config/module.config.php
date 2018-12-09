<?php
namespace Cartography;

return [
    'entity_manager' => [
        'resource_discriminator_map' => [
            Entity\DataTypeGeometry::class => Entity\DataTypeGeometry::class,
        ],
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
        // TODO Many Doctrine spatial functions are not used. Keep them to allow any dql queries?
        // The custom types are not loaded by the EntityManagerFactory, so they
        // are set in the bootstrap of the module.
        'functions' => [
            // See https://github.com/creof/doctrine2-spatial/blob/master/INSTALL.md.
            /*
            'string' => [
                // For postgresql.
                'geometry' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\Geometry::class,
                'stbuffer' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STBuffer::class,
                'stcollect' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCollect::class,
                'stsnaptogrid' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSnapToGrid::class,
                'stoverlaps' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STOverlaps::class,
            ],
            */
            'numeric' => [
                /*
                // For postgresql.
                'starea' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STArea::class,
                'stasbinary' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAsBinary::class,
                'stasgeojson' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAsGeoJson::class,
                'stastext' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAsText::class,
                'stazimuth' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STAzimuth::class,
                'stboundary' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STBoundary::class,
                'stcentroid' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCentroid::class,
                'stclosestpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STClosestPoint::class,
                'stcontains' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STContains::class,
                'stcontainsproperly' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STContainsProperly::class,
                'stcoveredby' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCoveredBy::class,
                'stcovers' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCovers::class,
                'stcrosses' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STCrosses::class,
                'stdisjoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDisjoint::class,
                'stdistance' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDistance::class,
                'stdistancesphere' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDistanceSphere::class,
                'stdwithin' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STDWithin::class,
                'stenvelope' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STEnvelope::class,
                'stexpand' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STExpand::class,
                'stextent' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STExtent::class,
                'stgeomfromtext' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STGeomFromText::class,
                'stintersection' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STIntersection::class,
                'stintersects' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STIntersects::class,
                'stlength' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STLength::class,
                'stlinecrossingdirection' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STLineCrossingDirection::class,
                'stlineinterpolatepoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STLineInterpolatePoint::class,
                'stmakebox2d' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STMakeBox2D::class,
                'stmakeline' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STMakeLine::class,
                'stmakepoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STMakePoint::class,
                'stperimeter' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STPerimeter::class,
                'stpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STPoint::class,
                'stscale' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STScale::class,
                'stsetsrid' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSetSRID::class,
                'stsimplify' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSimplify::class,
                'ststartpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STStartPoint::class,
                'stsummary' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STSummary::class,
                'sttouches' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STTouches::class,
                'sttransform' => \CrEOF\Spatial\ORM\Query\AST\Functions\PostgreSql\STTransform::class,
                */
                // For mysql.
                'area' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Area::class,
                'asbinary' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\AsBinary::class,
                'astext' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\AsText::class,
                'buffer' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Buffer::class,
                'centroid' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Centroid::class,
                'contains' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Contains::class,
                'crosses' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Crosses::class,
                'dimension' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Dimension::class,
                'distance' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Distance::class,
                'disjoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Disjoint::class,
                'distancefrommultyLine' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\DistanceFromMultyLine::class,
                'endpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\EndPoint::class,
                'envelope' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Envelope::class,
                'equals' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Equals::class,
                'exteriorring' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\ExteriorRing::class,
                'geodistpt' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GeodistPt::class,
                'geometrytype' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GeometryType::class,
                'geomfromtext' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GeomFromText::class,
                'glength' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\GLength::class,
                'interiorringn' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\InteriorRingN::class,
                'intersects' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Intersects::class,
                'isclosed' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\IsClosed::class,
                'isempty' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\IsEmpty::class,
                'issimple' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\IsSimple::class,
                'linestringfromwkb' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\LineStringFromWKB::class,
                'linestring' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\LineString::class,
                'mbrcontains' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRContains::class,
                'mbrdisjoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRDisjoint::class,
                'mbrequal' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBREqual::class,
                'mbrintersects' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRIntersects::class,
                'mbroverlaps' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBROverlaps::class,
                'mbrtouches' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRTouches::class,
                'mbrwithin' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\MBRWithin::class,
                'numinteriorrings' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\NumInteriorRings::class,
                'numpoints' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\NumPoints::class,
                'overlaps' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Overlaps::class,
                'pointfromwkb' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\PointFromWKB::class,
                'pointn' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\PointN::class,
                'point' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Point::class,
                'srid' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\SRID::class,
                'startpoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\StartPoint::class,
                'st_buffer' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STBuffer::class,
                'st_contains' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STContains::class,
                'st_crosses' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STCrosses::class,
                'st_disjoint' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STDisjoint::class,
                'st_distance' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STDistance::class,
                'st_equals' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STEquals::class,
                'st_intersects' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STIntersects::class,
                'st_overlaps' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STOverlaps::class,
                'st_touches' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STTouches::class,
                'st_within' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\STWithin::class,
                'touches' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Touches::class,
                'within' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Within::class,
                'x' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\X::class,
                'y' => \CrEOF\Spatial\ORM\Query\AST\Functions\MySql\Y::class,
                // Custom for this module (not yet available in Doctrine Spatial).
                'st_geomfromtext' => \Cartography\Doctrine\ORM\Query\AST\Functions\MySql\STGeomFromText::class,
            ],
        ],
    ],
    'data_types' => [
        'invokables' => [
            'geometry' => DataType\Geometry::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'controller_map' => [
            Controller\Admin\CartographyController::class => 'annotate/common/cartography',
            Controller\Site\CartographyController::class => 'annotate/common/cartography',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'cartography' => View\Helper\Cartography::class,
        ],
        'factories' => [
            'hasValueSuggest' => Service\ViewHelper\HasValueSuggestFactory::class,
            'normalizeGeometryQuery' => Service\ViewHelper\NormalizeGeometryQueryFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\CartographyController::class => Controller\Admin\CartographyController::class,
            Controller\Site\CartographyController::class => Controller\Site\CartographyController::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'imageSize' => Service\ControllerPlugin\ImageSizeFactory::class,
        ],
    ],
    'navigation' => [
        'AdminResource' => [
            'annotate' => [
                // Copy of the first level of navigation from the config of the module Annotate.
                // It avoids an error when Annotate is automatically disabled for upgrading.
                // This errors occurs one time only anyway.
                'label' => 'Annotations', // @translate
                'class' => 'annotations far fa-hand-o-up',
                'route' => 'admin/annotate/default',
                'resource' => \Annotate\Controller\Admin\AnnotationController::class,
                'privilege' => 'browse',
                'pages' => [
                    [
                        'route' => 'admin/annotate/id',
                        'controller' => \Annotate\Controller\Admin\AnnotationController::class,
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/annotate/default',
                        'controller' => \Annotate\Controller\Admin\AnnotationController::class,
                        'visible' => false,
                    ],
                    [
                        'label' => 'Cartography', // @translate
                        'route' => 'admin/cartography/default',
                        'resource' => Controller\Admin\CartographyController::class,
                        'privilege' => 'browse',
                        // 'class' => 'o-icon-map',
                        'pages' => [
                            [
                                'route' => 'admin/cartography/default',
                                'visible' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'cartography' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/cartography',
                            'defaults' => [
                                '__NAMESPACE__' => 'Cartography\Controller\Site',
                                'controller' => Controller\Site\CartographyController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'cartography' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/cartography',
                            'defaults' => [
                                '__NAMESPACE__' => 'Cartography\Controller\Admin',
                                'controller' => Controller\Admin\CartographyController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Zend\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'js_translate_strings' => [
        '[Untitled]', // @translate
        'Annotation #', // @translate
        'Cancel', // @translate
        'Cancel Styling', // @translate
        'Choose another element you want to style', // @translate
        'Click on the element you want to style', // @translate
        'Finish', // @translate
        'Image #', // @translate
        'Layer', // @translate
        'Log in to delete the geometry.', // @translate
        'Log in to edit the geometry.', // @translate
        'Log in to save the geometry.', // @translate
        'No overlay', // @translate
        'Related item', // @translate
        'Related items', // @translate
        'Related items:', // @translate
        'Remove value', // @translate
        'Save', // @translate
        'Save Styling', // @translate
        'The resource is already linked to the current annotation.', // @translate
        'There is no image attached to this resource.', // @translate
        'Unable to delete the geometry.', // @translate
        'Unable to delete the geometry: no identifier.', // @translate
        'Unable to fetch the geometries.', // @translate
        'Unable to find the geometry.', // @translate
        'Unable to save the geometry.', // @translate
        'Unable to save the edited geometry: no identifier.', // @translate
        'Unable to update the geometry.', // @translate
        'Uncertainty:', // @translate
    ],
    'cartography' => [
        'settings' => [
            'cartography_user_guide' => 'Feel free <strong>to annotate</strong> images and <strong>to locate</strong> resources!', // @translate
            'cartography_display_tab' => [
                'describe',
                'locate',
            ],
            // For easier install/upgrade, the values are the label, but they
            // are saved as id in fact.
            'cartography_template_describe' => [
                'Annotation describe',
            ],
            'cartography_template_describe_empty' => false,
            // For easier install/upgrade, the values are the label, but they
            // are saved as id in fact.
            'cartography_template_locate' => [
                'Annotation locate',
            ],
            'cartography_template_locate_empty' => false,
            'cartography_js_describe' => '',
            'cartography_js_locate' => '',
        ],
        'site_settings' => [
            'cartography_append_public' => [
                'describe_item_sets_show',
                'describe_items_show',
                'describe_media_show',
                'locate_item_sets_show',
                'locate_items_show',
                'locate_media_show',
            ],
            'cartography_annotate' => false,
        ],
    ],
];
