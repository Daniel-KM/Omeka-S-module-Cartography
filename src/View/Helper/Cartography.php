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
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $options Associative array of params:
     * - type (string): string, "locate" (default) or "describe"
     * - annotate (bool): allow user or visitor to annotate (default: false)
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

        $isPublic = (bool) $view->params()->fromRoute('__SITE__');
        $options['is_public'] = $isPublic;

        if ($options['annotate']) {
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
        }

        $options['baseUrl'] = $options['is_public']
            ? '/s/' . $view->params()->fromRoute('site-slug')
            : '/admin';

        echo $view->partial(
            'common/cartography',
            [
                'resource' => $resource,
                'options' => $options,
            ]
        );
    }
}
