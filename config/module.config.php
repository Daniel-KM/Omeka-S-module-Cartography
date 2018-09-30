<?php
namespace Cartography;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'cartography' => Service\ViewHelper\CartographyFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\CartographyController::class => Controller\Admin\CartographyController::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'cartography' => [
                        'type' => \Zend\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/cartography',
                            'defaults' => [
                                '__NAMESPACE__' => 'Cartography\Controller\Admin',
                                'controller' => 'CartographyController',
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
    'cartography' => [
        'config' => [
            'cartography_user_guide' => 'Feel free to use <strong>Cartography</strong>!', // @translate
            'cartography_display_tab' => ['describe', 'locate'],
            'cartography_js_describe' => '',
            'cartography_js_locate' => '',
        ],
        'site_settings' => [
            'cartography_append_item_set_show' => true,
            'cartography_append_item_show' => true,
            'cartography_append_media_show' => true,
        ],
        'dependencies' => [
            'Annotate',
        ],
    ],
];
