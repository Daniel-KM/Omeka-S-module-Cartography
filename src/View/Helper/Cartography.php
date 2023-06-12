<?php declare(strict_types=1);

namespace Cartography\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;

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
            || (isset($options['sections']) && in_array('geobrowse', $options['sections']))
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
        $assetUrl = $view->plugin('assetUrl');

        // TODO Public annotation is not yet available: should manage the resource selector sidebar and rights.
        $annotate = $options['annotate'];
        $geoBrowse = $options['type'] === 'geobrowse';
        $isPublic = (bool) $view->params()->fromRoute('__SITE__');
        $options['currentPath'] = $isPublic
            ? 's/' . $view->params()->fromRoute('site-slug')
            : 'admin';

        // The module is independant from the module Mapping, but some js are the same,
        // so it is recommenced to choose one module or the other to avoid js conflicts.
        $headLink
            ->appendStylesheet($assetUrl('vendor/leaflet/leaflet.css', 'Cartography'));
        $headScript
        ->appendFile($assetUrl('vendor/leaflet/leaflet.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);

        // Add specific code for annotation.
        if ($annotate) {
            $user = $view->identity();
            $rights = $this->globalRights($user, $annotate);

            // Edition via draw (used for creation, edit or delete).
            $headLink
                ->appendStylesheet($assetUrl('vendor/leaflet-draw/leaflet.draw.css', 'Cartography'));
            $headScript
                ->appendFile($assetUrl('vendor/leaflet-draw/leaflet.draw.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);

            // TODO Check if valuesuggest is used in one of the annotation templates.
            if ($view->hasValueSuggest()) {
                // $event = new \Laminas\EventManager\Event('cartography.add_value_suggest', $view);
                // (new \ValueSuggest\Module)->prepareResourceForm($event);
                $headLink
                    ->appendStylesheet($assetUrl('css/valuesuggest.css', 'ValueSuggest'));
                $headScript
                    ->appendFile($assetUrl('js/jQuery-Autocomplete/1.2.26/jquery.autocomplete.min.js', 'ValueSuggest'), 'text/javascript', ['defer' => 'defer'])
                    ->appendFile($assetUrl('js/valuesuggest.js', 'ValueSuggest'), 'text/javascript', ['defer' => 'defer'])
                    ->appendScript(sprintf(
                        'var valueSuggestProxyUrl = "%s";',
                        $view->escapeJs($view->url('admin/value-suggest/proxy'))
                    ));
            }

            if ($rights['create']) {
                // Leaflet paste.
                $headLink
                    ->appendStylesheet($assetUrl('vendor/leaflet-paste/css/Leaflet.paste.css', 'Cartography'));
                $headScript
                    ->appendFile($assetUrl('vendor/leaflet-paste/vendor/wicket.src.js', 'Cartography'), 'text/javascript', ['defer' => 'defer'])
                    ->appendFile($assetUrl('vendor/leaflet-paste/vendor/wicket-leaflet.src.js', 'Cartography'), 'text/javascript', ['defer' => 'defer'])
                    ->appendFile($assetUrl('vendor/leaflet-paste/js/Leaflet.Layer.WKT.js', 'Cartography'), 'text/javascript', ['defer' => 'defer'])
                    ->appendFile($assetUrl('vendor/leaflet-paste/js/Leaflet.paste.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);
            }

            if ($rights['edit']) {
                // Style editor.
                $headLink
                    ->appendStylesheet($assetUrl('vendor/leaflet-styleeditor/css/Leaflet.StyleEditor.min.css', 'Cartography'));
                $headScript
                    ->appendFile($assetUrl('vendor/leaflet-styleeditor/javascript/Leaflet.StyleEditor.min.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);

                // TODO Load only the item selector part of the resource-form.js.
                $headScript
                    ->appendFile($assetUrl('js/resource-form.js', 'Omeka'), 'text/javascript', ['defer' => 'defer']);

                // TODO Integrate the resource selector sidebar in public view (or inside the style editor, that will allow full screen linking too).
                $html .= $view->partial('common/resource-select-sidebar');
            }
        }

        if ($geoBrowse) {
            // Just to display a square or a circle.
            $headLink
                ->appendStylesheet($assetUrl('vendor/leaflet-draw/leaflet.draw.css', 'Cartography'));
            $headScript
                ->appendFile($assetUrl('vendor/leaflet-draw/leaflet.draw.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);
        }

        // Leaflet terraformer.
        // TODO See if terraformer can replace leaflet paste.
        $headScript
            ->appendFile($assetUrl('vendor/terraformer/terraformer-1.0.12.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('vendor/terraformer-arcgis-parser/terraformer-arcgis-parser-1.1.0.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('vendor/terraformer-wkt-parser/terraformer-wkt-parser-1.2.1.min.js', 'DataTypeGeometry'), 'text/javascript', ['defer' => 'defer']);

        // Leaflet full screen (full view).
        $headLink
            ->appendStylesheet($assetUrl('vendor/leaflet-fullscreen/leaflet.fullscreen.css', 'Cartography'));
        $headScript
            ->appendFile($assetUrl('vendor/leaflet-fullscreen/Leaflet.fullscreen.min.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);

        // TODO Fit bounds and default view (js from module Mapping).
        // $headScript->appendFile($assetUrl('js/control.fit-bounds.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);
        // $headScript->appendFile($assetUrl('js/control.default-view.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);

        // For simplicity of the code, all headers are added, whatever the type.
        //  TODO Don't load the specific part of the headers if the type is not present in none of the blocks.

        // Headers for locate.

        // Geosearch.
        $headLink
            ->appendStylesheet($assetUrl('vendor/leaflet-geosearch/style.css', 'Cartography'))
            ->appendStylesheet($assetUrl('vendor/leaflet-geosearch/leaflet.css', 'Cartography'));
        $headScript
            ->appendFile($assetUrl('vendor/leaflet-providers/leaflet-providers.min.js', 'Cartography'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('vendor/leaflet-geosearch/bundle.min.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);

        // Add wms layers if any (may be added in the config or in the resource).
        // TODO Load wms js only if there are wms layers (the list is loaded dynamically).
        $headLink
            ->appendStylesheet($assetUrl('vendor/leaflet-markercluster/MarkerCluster.css', 'Cartography'))
            ->appendStylesheet($assetUrl('vendor/leaflet-markercluster/MarkerCluster.Default.css', 'Cartography'));
        $headScript
            ->appendFile($assetUrl('vendor/leaflet-markercluster/leaflet.markercluster.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);
        $headLink
            ->appendStylesheet($assetUrl('vendor/leaflet-groupedlayercontrol/leaflet.groupedlayercontrol.min.css', 'Cartography'));
        $headScript
            ->appendFile($assetUrl('vendor/leaflet-groupedlayercontrol/leaflet.groupedlayercontrol.min.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);
        // From module Mapping.
        $headScript->appendFile($assetUrl('js/control.opacity.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);

        if ($js = $view->setting('cartography_js_locate')) {
            // Add wmts layers if needed.
            $headScript
                ->appendFile($assetUrl('vendor/leaflet-tilelayer-wmts/leaflet-tilelayer-wmts.min.js', 'Cartography'), 'text/javascript', ['defer' => 'defer'])
                ->appendScript('$(document).ready( function() { ' . $js . ' });');
        }

        // Headers for describe.

        if ($js = $view->setting('cartography_js_describe')) {
            $headScript
                ->appendScript('$(document).ready( function() { ' . $js . ' });');
        }

        // More common headers.
        $headLink
            ->appendStylesheet($assetUrl('css/cartography.css', 'Cartography'));
        $headScript
            ->appendFile($assetUrl('js/cartography.js', 'Cartography'), 'text/javascript', ['defer' => 'defer']);

        // Integration in Omeka S.
        $script = '';
        if ($isPublic) {
            $script .= '
var Omeka = {};';
        }

        $script .= 'const currentPath = ' . json_encode($options['currentPath'], 320) . ';
const cartographySections = ' . json_encode($options['sections'], 320) . ';';

        if ($resource):
            $script .= '
const resourceId = ' . $resource->id() . ';';
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
                    $terms = $customVocab->terms();
                    $options[$key] = is_array($terms) ? $terms : explode(PHP_EOL, $terms);
                } catch (NotFoundException $e) {
                    $options[$key] = [];
                }
            }

            $user = $view->identity();
            $script .= '
const userId = ' . ($user ? $user->getId() : 0) . ';
const userRights = ' . json_encode($rights, 320) . ';';
            if ($resource):
                $script .= '
const valuesJson = ' . json_encode($resource->values(), 320) . ';';
            endif;
            $script .= '
const oaMotivatedBySelect = ' . json_encode($options['oaMotivatedBySelect'], 320) . ';
const oaHasPurposeSelect = ' . json_encode($options['oaHasPurposeSelect'], 320) . ';
const cartographyUncertaintySelect = ' . json_encode($options['cartographyUncertaintySelect'], 320) . ';';
        } else {
            $script .= '
const userId = 0;
const userRights = {"create":false,"edit":false,"delete":false};';
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
