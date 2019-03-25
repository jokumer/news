<?php

namespace GeorgRinger\News\ViewHelpers\Widget;

/**
 * This file is part of the "news" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * A view helper for creating URIs to extbase actions within widgets.
 * Copied from TYPO3 9.5.1 EXT:fluid/Classes/ViewHelpers/Widget/UriViewHelper.php
 *
 * Reduces links by unwanted params like empty ones and POST referrer & trustedProperties
 * When using addQueryStringMethod = GET,POST
 * See usage of method getArgumentsToBeExcluded()
 *
 * = Examples =
 *
 * <code title="URI to the show-action of the current controller">
 * <f:widget.uri action="show" />
 * </code>
 * <output>
 * index.php?id=123&tx_myextension_plugin[widgetIdentifier][action]=show&tx_myextension_plugin[widgetIdentifier][controller]=Standard&cHash=xyz
 * (depending on the current page, widget and your TS configuration)
 * </output>
 */
class UriViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('useCacheHash', 'bool', 'True whether the cache hash should be appended to the URL', false, false);
        $this->registerArgument('addQueryStringMethod', 'string', 'Method to be used for query string');
        $this->registerArgument('action', 'string', 'Target action');
        $this->registerArgument('arguments', 'array', 'Arguments', false, []);
        $this->registerArgument('section', 'string', 'The anchor to be added to the URI', false, '');
        $this->registerArgument('format', 'string', 'The requested format, e.g. ".html', false, '');
        $this->registerArgument('ajax', 'bool', 'TRUE if the URI should be to an AJAX widget, FALSE otherwise.', false, false);
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $ajax = $arguments['ajax'];

        if ($ajax === true) {
            return static::getAjaxUri($renderingContext, $arguments);
        }
        return static::getWidgetUri($renderingContext, $arguments);
    }

    /**
     * Get the URI for an AJAX Request.
     *
     * @param RenderingContextInterface $renderingContext
     * @param array $arguments
     * @return string the AJAX URI
     */
    protected static function getAjaxUri(RenderingContextInterface $renderingContext, array $arguments)
    {
        $controllerContext = $renderingContext->getControllerContext();
        $action = $arguments['action'];
        $arguments = $arguments['arguments'];
        if ($action === null) {
            $action = $controllerContext->getRequest()->getControllerActionName();
        }
        $arguments['id'] = $GLOBALS['TSFE']->id;
        // @todo page type should be configurable
        $arguments['type'] = 7076;
        $arguments['fluid-widget-id'] = $controllerContext->getRequest()->getWidgetContext()->getAjaxWidgetIdentifier();
        $arguments['action'] = $action;
        return '?' . http_build_query($arguments, null, '&');
    }

    /**
     * Get the URI for a non-AJAX Request.
     *
     * @param RenderingContextInterface $renderingContext
     * @param array $arguments
     * @return string the Widget URI
     */
    protected static function getWidgetUri(RenderingContextInterface $renderingContext, array $arguments)
    {
        $controllerContext = $renderingContext->getControllerContext();
        $uriBuilder = $controllerContext->getUriBuilder();
        $argumentPrefix = $controllerContext->getRequest()->getArgumentPrefix();
        $parameters = $arguments['arguments'] ?? [];
        if ($arguments['action'] ?? false) {
            $parameters['action'] = $arguments['action'];
        }
        if (($arguments['format'] ?? '') !== '') {
            $parameters['format'] = $arguments['format'];
        }
        return $uriBuilder->reset()
            ->setArguments([$argumentPrefix => $parameters])
            ->setSection($arguments['section'])
            ->setUseCacheHash($arguments['useCacheHash'])
            ->setAddQueryString(true)
            ->setAddQueryStringMethod($arguments['addQueryStringMethod'])
            ->setArgumentsToBeExcludedFromQueryString(static::getArgumentsToBeExcluded($renderingContext))
            ->setFormat($arguments['format'])
            ->build();
    }

    /**
     * Get arguments to be excluded
     * Used for search including pagination.
     * When GET/POST parameters are added to link builder ->setAddQueryStringMethod('GET,POST'),
     * then we need to exclude unwanted params from POST like referrer & trustedProperties.
     * Furthermore, this finds empty search values and excludes its also for link generation
     * This way, our links will be shorten as possible
     *
     * @param RenderingContextInterface $renderingContext
     * @return string the Widget URI
     */
    protected static function getArgumentsToBeExcluded(RenderingContextInterface $renderingContext)
    {
        // Set plugin prefix
        $pluginPrefix = 'tx_news_pi1';
        // Default arguments to be excluded
        $argumentsToBeExcluded = [
            $renderingContext->getControllerContext()->getRequest()->getArgumentPrefix(),
            'cHash'
        ];
        // GET POST arguments to be excluded
        $GPVars = GeneralUtility::_GP($pluginPrefix);
        if (!empty($GPVars)) {
            // Exclude POST __referrer
            if (isset($GPVars['__referrer'])) {
                $argumentsToBeExcluded[] = $pluginPrefix . '[__referrer]';
            }
            // Exclude POST __trustedProperties
            if (isset($GPVars['__trustedProperties'])) {
                $argumentsToBeExcluded[] = $pluginPrefix . '[__trustedProperties]';
            }
            // Exclude GET/POST search which are empty
            if (isset($GPVars['search']) && !empty($GPVars['search'])) {
                foreach ($GPVars['search'] as $searchKey => $searchValue) {
                    switch ($searchKey) {
                        // Exclude empty subject string
                        case 'subject':
                            if (empty($searchValue)) {
                                $argumentsToBeExcluded[] = $pluginPrefix . '[search][subject]';
                            }
                            break;
                        // Exclude empty category & tag values
                        case 'categories':
                        case 'tags':
                            if (!empty($searchValue)) {
                                foreach ($searchValue as $key => $value) {
                                    if (empty($value)) {
                                        $argumentsToBeExcluded[] = $pluginPrefix . '[search][' . $searchKey . '][' . $key . ']';
                                    }
                                }
                            }
                            break;
                    }
                }
            }
        }
        return $argumentsToBeExcluded;
    }
}
