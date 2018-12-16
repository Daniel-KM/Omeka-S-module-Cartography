<?php
namespace Cartography\View\Helper;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;
use Zend\View\Helper\AbstractHelper;

class Cartography extends AbstractHelper
{
    /**
     * Return the partial to display the cartography of a resource.
     *
     * This helper manages three main parameters: describe/locate,
     * display/annotate and public/admin. It should avoid to reload all the
     * headers in case of multiple blocks.
     *
     * @param AbstractResourceEntityRepresentation|null $resource Null to
     * geobrowse.
     * @param array $options Associative array of params:
     * - type (string): string, "locate" (default), "describe" or "geobrowse"
     * (that is exclusive)
     * - annotate (bool): display the toolbar to create/edit/delete, if the user
     * has the rights (default: false).
     * Next params will be removed in a future release.
     * - headers (bool): prepend headers or not (default: true), that is useful
     * when there are multiple blocks).
     * - sections (array): list of sections to display (used only to manage
     * headers). Automatically set to the type if not set. Used internally.
     * @todo Simplify the load of headers and sections.
     * @return string The html string.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource = null, array $options = [])
    {
        $view = $this->getView();

        // Geobrowse is exclusive.
        if (empty($resource)
            || (isset($options['type']) && $options['type'] === 'geobrowse')
            || (isset($options['sections']) && in_array('geobrowse' , $options['sections']))
        ) {
            $options = [
                'type' => 'geobrowse',
                'annotate' => false,
                'headers' => true,
                'sections' => ['geobrowse'],
            ];
        } else {
            $default = [
                'type' => 'locate',
                'annotate' => false,
                'headers' => true,
                'sections' => ['describe', 'locate'],
            ];
            $issetSections = isset($options['sections']);
            $options = array_merge($default, $options);
            if (!$issetSections) {
                $options['sections'] = [$options['type']];
            }
        }

        $html = $options['headers'] ? $this->prepareHeaders($resource, $options) : '';
        unset($options['headers']);
        unset($options['sections']);

        if (empty($resource)) {
            return $options;
        }

        $isPublic = (bool) $view->params()->fromRoute('__SITE__');
        $html .= $isPublic
            ? $view->partial('common/cartography-public', ['resource' => $resource, 'options' => $options])
            : $view->partial('common/cartography-admin', ['resource' => $resource, 'options' => $options]);
        return $html;
    }

    /**
     * Prepare headers and return html needed by partial dependecies.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options
     * @return string
     */
    protected function prepareHeaders(AbstractResourceEntityRepresentation $resource = null, array $options)
    {
        $html = '';

        $view = $this->getView();
        $headLink = $view->headLink();
        $headScript = $view->headScript();

        // TODO Public annotation is not yet available: should manage the resource selector sidebar and rights.
        $annotate = $options['annotate'];
        $geoBrowse = $options['type'] === 'geobrowse';
        $isPublic = (bool) $view->params()->fromRoute('__SITE__');
        $options['baseUrl'] = $isPublic
            ? '/s/' . $view->params()->fromRoute('site-slug')
            : '/admin';

        // The module is independant from the module Mapping, but some js are the same,
        // so it is recommenced to choose one module or the other to avoid js conflicts.
        $headLink->appendStylesheet($view->assetUrl('vendor/leaflet/leaflet.css', 'Cartography'));
        $headScript->appendFile($view->assetUrl('vendor/leaflet/leaflet.js', 'Cartography'));

        // Add specific code for annotation.
        if ($annotate) {
            $user = $view->identity();
            $rights = $this->globalRights($user, $annotate);

            // Edition via draw (used for creation, edit or delete).
            $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-draw/leaflet.draw.css', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-draw/leaflet.draw.js', 'Cartography'));

            // TODO Check if valuesuggest is used in one of the annotation templates.
            if ($view->hasValueSuggest()) {
                // $event = new \Zend\EventManager\Event('cartography.add_value_suggest', $view);
                // (new \ValueSuggest\Module)->prepareResourceForm($event);
                $headLink->appendStylesheet($view->assetUrl('css/valuesuggest.css', 'ValueSuggest'));
                $headScript->appendFile($view->assetUrl('js/jQuery-Autocomplete/1.2.26/jquery.autocomplete.min.js', 'ValueSuggest'));
                $headScript->appendFile($view->assetUrl('js/valuesuggest.js', 'ValueSuggest'));
                $headScript->appendScript(sprintf(
                    'var valueSuggestProxyUrl = "%s";',
                    $view->escapeJs($view->url('admin/value-suggest/proxy'))
                ));
            }

            if ($rights['create']) {
                // Leaflet paste.
                $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-paste/css/Leaflet.paste.css', 'Cartography'));
                $headScript->appendFile($view->assetUrl('vendor/leaflet-paste/vendor/wicket.src.js', 'Cartography'));
                $headScript->appendFile($view->assetUrl('vendor/leaflet-paste/vendor/wicket-leaflet.src.js', 'Cartography'));
                $headScript->appendFile($view->assetUrl('vendor/leaflet-paste/js/Leaflet.Layer.WKT.js', 'Cartography'));
                $headScript->appendFile($view->assetUrl('vendor/leaflet-paste/js/Leaflet.paste.js', 'Cartography'));
            }

            if ($rights['edit']) {
                // Style editor.
                $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-styleeditor/css/Leaflet.StyleEditor.min.css', 'Cartography'));
                $headScript->appendFile($view->assetUrl('vendor/leaflet-styleeditor/javascript/Leaflet.StyleEditor.min.js', 'Cartography'));

                // TODO Load only the item selector part of the resource-form.js.
                $headScript->appendFile($view->assetUrl('js/resource-form.js', 'Omeka'));

                // TODO Integrate the resource selector sidebar in public view (or inside the style editor, that will allow full screen linking too).
                $html .= $view->partial('common/resource-select-sidebar');
            }
        }

        if ($geoBrowse) {
            // Just to display a square or a circle.
            $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-draw/leaflet.draw.css', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-draw/leaflet.draw.js', 'Cartography'));
        }

        // Leaflet terraformer.
        // TODO See if terraformer can replace leaflet paste.
        $headScript->appendFile($view->assetUrl('vendor/terraformer/terraformer.min.js', 'DataTypeGeometry'));
        $headScript->appendFile($view->assetUrl('vendor/terraformer-arcgis-parser/terraformer-arcgis-parser.min.js', 'DataTypeGeometry'));
        $headScript->appendFile($view->assetUrl('vendor/terraformer-wkt-parser/terraformer-wkt-parser.min.js', 'DataTypeGeometry'));

        // Leaflet full screen (full view).
        $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-fullscreen/leaflet.fullscreen.css', 'Cartography'));
        $headScript->appendFile($view->assetUrl('vendor/leaflet-fullscreen/Leaflet.fullscreen.min.js', 'Cartography'));

        // TODO Fit bounds and default view (js from module Mapping).
        // $headScript->appendFile($view->assetUrl('js/control.fit-bounds.js', 'Cartography'));
        // $headScript->appendFile($view->assetUrl('js/control.default-view.js', 'Cartography'));

        // For simplicity of the code, all headers are added, whatever the type.
        //  TODO Don't load the specific part of the headers if the type is not present in none of the blocks.

        // Headers for locate.

        // Geosearch.
        $headScript->appendFile($view->assetUrl('vendor/leaflet-providers/leaflet-providers.min.js', 'Cartography'));
        $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-geosearch/style.css', 'Cartography'));
        $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-geosearch/leaflet.css', 'Cartography'));
        $headScript->appendFile($view->assetUrl('vendor/leaflet-geosearch/bundle.min.js', 'Cartography'));

        // Add wms layers if any (may be added in the config or in the resource).
        // TODO Load wms js only if there are wms layers (the list is loaded dynamically).
        $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-markercluster/MarkerCluster.css', 'Cartography'));
        $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-markercluster/MarkerCluster.Default.css', 'Cartography'));
        $headScript->appendFile($view->assetUrl('vendor/leaflet-markercluster/leaflet.markercluster.js', 'Cartography'));
        $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-groupedlayercontrol/leaflet.groupedlayercontrol.min.css', 'Cartography'));
        $headScript->appendFile($view->assetUrl('vendor/leaflet-groupedlayercontrol/leaflet.groupedlayercontrol.min.js', 'Cartography'));
        // From module Mapping.
        $headScript->appendFile($view->assetUrl('js/control.opacity.js', 'Cartography'));

        if ($js = $view->setting('cartography_js_locate')) {
            // Add wmts layers if needed.
            // $headScript->appendFile($view->assetUrl('vendor/leaflet-tilelayer-wmts/leaflet-tilelayer-wmts.js', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-tilelayer-wmts/leaflet-tilelayer-wmts-src.js', 'Cartography'));
            $headScript->appendScript('$(document).ready( function() { ' . $js . ' });');
        }

        // Headers for describe.

        if ($js = $view->setting('cartography_js_describe')) {
            $headScript->appendScript('$(document).ready( function() { ' . $js . ' });');
        }

        // More common headers.
        $headLink->appendStylesheet($view->assetUrl('css/cartography.css', 'Cartography'));
        $headScript->appendFile($view->assetUrl('js/cartography.js', 'Cartography'));

        // Integration in Omeka S.
        $script = '';
        if ($isPublic) {
            $script .= '
var Omeka = {};';
        }

        $script .= 'var basePath = ' . json_encode($view->basePath(), 320) . ';
var baseUrl = ' . json_encode($options['baseUrl'], 320) . ';
var cartographySections = ' . json_encode($options['sections'], 320). ';';
        if ($resource):
            $script .= '
var resourceId = ' . $resource->id() . ';';
        endif;

        if ($annotate) {
            $customVocabs = [
                'oaMotivatedBySelect' => 'Annotation oa:motivatedBy',
                'oaHasPurposeSelect' => 'Annotation Body oa:hasPurpose',
                'cartographyUncertaintySelect' => 'Cartography cartography:uncertainty',
            ];
            $api = $view->api();
            foreach ($customVocabs as $key => $label) {
                try {
                    $customVocab = $api->read('custom_vocabs', [
                        'label' => $label,
                    ])->getContent();
                    $options[$key] = explode(PHP_EOL, $customVocab->terms());
                } catch (NotFoundException $e) {
                    $options[$key] = [];
                }
            }

            $user = $view->identity();
            $script .= '
var userId = ' . ($user ? $user->getId() : 0) . ';
var userRights = ' . json_encode($rights, 320) . ';';
            if ($resource):
                $script .= '
var valuesJson =  ' . json_encode($resource->values(), 320). ';';
            endif;
            $script .= '
var oaMotivatedBySelect = ' . json_encode($options['oaMotivatedBySelect'], 320) . ';
var oaHasPurposeSelect = ' . json_encode($options['oaHasPurposeSelect'], 320) . ';
var cartographyUncertaintySelect = ' . json_encode($options['cartographyUncertaintySelect'], 320) . ';';
        } else {
            $script .= '
var userId = 0;
var userRights = {"create":false,"edit":false,"delete":false};';
        }

        $headScript->appendScript($script);

        // Append a specific script to translate public pages.
        if ($isPublic) {
            $view->jsTranslate();
        }

        return $html;
    }

    /**
     * Get the global rights of a user.
     *
     * @param User $user
     * @param bool $annotate
     * @return array
     */
    protected function globalRights(User $user = null, $annotate = false)
    {
        // TODO Get the rights directly from the acl, not from the role.
        $role = $user ? $user->getRole() : 'annotator_visitor';
        switch ($role) {
            case \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN:
            case \Omeka\Permissions\Acl::ROLE_SITE_ADMIN:
            case \Omeka\Permissions\Acl::ROLE_EDITOR:
                $rights = [
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                ];
                break;
            case \Omeka\Permissions\Acl::ROLE_REVIEWER:
                $rights = [
                    'create' => true,
                    'edit' => true,
                    'delete' => 'own',
                ];
                break;
            case \Omeka\Permissions\Acl::ROLE_AUTHOR:
            case \Omeka\Permissions\Acl::ROLE_RESEARCHER:
            case \Annotate\Permissions\Acl::ROLE_ANNOTATOR:
                $rights = [
                    'create' => true,
                    'edit' => 'own',
                    'delete' => 'own',
                ];
                break;
            case 'annotator_visitor':
            case 'guest':
                $rights = [
                'create' => $annotate,
                'edit' => $annotate ? 'own' : false,
                'delete' => $annotate ? 'own' : false,
                ];
                break;
            default:
                $rights = [
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                ];
                break;
        }

        return $rights;
    }
}
