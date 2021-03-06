<?php

/**
 * This file is part of the FOSElasticaBundle project.
 *
 * (c) Infinite Networks Pty Ltd <http://www.infinite.net.au>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Index;

use FOS\ElasticaBundle\Configuration\IndexConfig;
use FOS\ElasticaBundle\Configuration\TypeConfig;

class MappingBuilder
{
    /**
     * Skip adding default information to certain fields.
     *
     * @var array
     */
    private $skipTypes = array('completion');

    /**
     * Builds mappings for an entire index.
     *
     * @param IndexConfig $indexConfig
     *
     * @return array
     */
    public function buildIndexMapping(IndexConfig $indexConfig)
    {
        $typeMappings = array();
        foreach ($indexConfig->getTypes() as $typeConfig) {
            $typeMappings[$typeConfig->getName()] = $this->buildTypeMapping($typeConfig);
        }

        $mapping = array();
        if ($typeMappings) {
            $mapping['mappings'] = $typeMappings;
        }
        // 'warmers' => $indexConfig->getWarmers(),

        $settings = $indexConfig->getSettings();
        if ($settings) {
            $mapping['settings'] = $settings;
        }

        return $mapping;
    }

    /**
     * Builds mappings for a single type.
     *
     * @param TypeConfig $typeConfig
     *
     * @return array
     */
    public function buildTypeMapping(TypeConfig $typeConfig)
    {
        $mapping = array_merge($typeConfig->getMapping(), array(
            // 'date_detection' => true,
            // 'dynamic_date_formats' => array()
            // 'dynamic_templates' => $typeConfig->getDynamicTemplates(),
            // 'numeric_detection' => false,
            // 'properties' => array(),
        ));

        if ($typeConfig->getIndexAnalyzer()) {
            $mapping['index_analyzer'] = $typeConfig->getIndexAnalyzer();
        }

        if ($typeConfig->getSearchAnalyzer()) {
            $mapping['search_analyzer'] = $typeConfig->getSearchAnalyzer();
        }

        if (isset($mapping['dynamic_templates']) and empty($mapping['dynamic_templates'])) {
            unset($mapping['dynamic_templates']);
        }

        $this->fixProperties($mapping['properties']);
        if (!$mapping['properties']) {
            unset($mapping['properties']);
        }

        if ($typeConfig->getModel()) {
            $mapping['_meta']['model'] = $typeConfig->getModel();
        }

        if (!$mapping) {
            // Empty mapping, we want it encoded as a {} instead of a []
            $mapping = new \stdClass();
        }

        return $mapping;
    }

    /**
     * Fixes any properties and applies basic defaults for any field that does not have
     * required options.
     *
     * @param $properties
     */
    private function fixProperties(&$properties)
    {
        foreach ($properties as $name => &$property) {
            if (!isset($property['type'])) {
                $property['type'] = 'string';
            }
            if ($property['type'] == 'multi_field' && isset($property['fields'])) {
                $this->fixProperties($property['fields']);
            }
            if (isset($property['properties'])) {
                $this->fixProperties($property['properties']);
            }
            if (in_array($property['type'], $this->skipTypes)) {
                continue;
            }
            if (!isset($property['store'])) {
                $property['store'] = true;
            }
        }
    }
}
