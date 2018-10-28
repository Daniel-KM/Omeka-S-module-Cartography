<?php
namespace Cartography\View\Helper;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
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
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options Associative array of params:
     * - type (string): string, "locate" (default) or "describe"
     * - annotate (bool): allow user or visitor to annotate (default: false)
     * Next params will be removed in a future release.
     * - headers (bool): prepend headers or not (default: true), that is useful
     * when there are multiple blocks).
     * - sections (array): list of sections to display (used only to manage
     * headers). Automatically set to the type if not set. Used internally.
     * @todo Simplify the load of headers and sections.
     * @return string The html string.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = [])
    {
        $view = $this->getView();

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

        $html = $options['headers'] ? $this->prepareHeaders($resource, $options) : '';
        unset($options['headers']);
        unset($options['sections']);

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
    protected function prepareHeaders(AbstractResourceEntityRepresentation $resource, array $options)
    {
        $html = '';

        $view = $this->getView();
        $headLink = $view->headLink();
        $headScript = $view->headScript();

        // TODO Public annotation is not available: should manage the resource selector sidebar and rights.
        $annotate = $options['annotate'];
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
            // Edition via draw.
            $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-draw/leaflet.draw.css', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-draw/leaflet.draw.js', 'Cartography'));

            // Style editor.
            $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-styleeditor/css/Leaflet.StyleEditor.min.css', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-styleeditor/javascript/Leaflet.StyleEditor.min.js', 'Cartography'));

            // Leaflet paste.
            $headLink->appendStylesheet($view->assetUrl('vendor/leaflet-paste/css/Leaflet.paste.css', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-paste/vendor/wicket.src.js', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-paste/vendor/wicket-leaflet.src.js', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-paste/js/Leaflet.Layer.WKT.js', 'Cartography'));
            $headScript->appendFile($view->assetUrl('vendor/leaflet-paste/js/Leaflet.paste.js', 'Cartography'));

            // TODO Load only the item selector part of the resource-form.js.
            $headScript->appendFile($view->assetUrl('js/resource-form.js', 'Omeka'));

            // TODO Integrate the resource selector sidebar in public view (or inside the style editor, that will allow full screen linking too).
            $html .= $view->partial('common/resource-select-sidebar');
        }

        // Leaflet terraformer.
        // TODO See if terraformer can replace leaflet paste.
        $headScript->appendFile($view->assetUrl('vendor/terraformer/terraformer.min.js', 'Cartography'));
        $headScript->appendFile($view->assetUrl('vendor/terraformer-arcgis-parser/terraformer-arcgis-parser.min.js', 'Cartography'));
        $headScript->appendFile($view->assetUrl('vendor/terraformer-wkt-parser/terraformer-wkt-parser.min.js', 'Cartography'));

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
var resourceId = ' . $resource->id() . ';
var cartographySections = ' . json_encode($options['sections'], 320). ';
var rightAnnotate = ' . ($annotate ? 'true' : 'false') . ';';

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

            $script .= '
var valuesJson =  ' . json_encode($resource->values(), 320). ';
var oaMotivatedBySelect = ' . json_encode($options['oaMotivatedBySelect'], 320) . ';
var oaHasPurposeSelect = ' . json_encode($options['oaHasPurposeSelect'], 320) . ';
var cartographyUncertaintySelect = ' . json_encode($options['cartographyUncertaintySelect'], 320) . ';';
        }

        $headScript->appendScript($script);

        // Append a specific script to translate public pages.
        if ($isPublic) {
            $view->jsTranslate();
        }

        return $html;
    }
}
