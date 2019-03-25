<?php

namespace GeorgRinger\News\ViewHelpers\Widget;

/**
 * This file is part of the "news" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * A view helper for creating Links to extbase actions within widgets
 * Copied from TYPO3 9.5.1 EXT:fluid/Classes/ViewHelpers/Widget/LinkViewHelper.php
 * 
 * Reduces links by unwanted params like empty ones and POST referrer & trustedProperties
 * When using addQueryStringMethod = GET,POST
 * See usage of method getArgumentsToBeExcluded()
 *
 * = Examples =
 *
 * <code title="URI to the show-action of the current controller">
 * <de:widget.link action="show">link</de:widget.link>
 * </code>
 * <output>
 * <a href="index.php?id=123&tx_myextension_plugin[widgetIdentifier][action]=show&tx_myextension_plugin[widgetIdentifier][controller]=Standard&cHash=xyz">link</a>
 * (depending on the current page, widget and your TS configuration)
 * </output>
 */
class LinkViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('name', 'string', 'Specifies the name of an anchor');
        $this->registerTagAttribute('rel', 'string', 'Specifies the relationship between the current document and the linked document');
        $this->registerTagAttribute('rev', 'string', 'Specifies the relationship between the linked document and the current document');
        $this->registerTagAttribute('target', 'string', 'Specifies where to open the linked document');
        $this->registerArgument('useCacheHash', 'bool', 'True whether the cache hash should be appended to the URL', false, false);
        $this->registerArgument('addQueryStringMethod', 'string', 'Method to be used for query string');
        $this->registerArgument('action', 'string', 'Target action');
        $this->registerArgument('arguments', 'array', 'Arguments', false, []);
        $this->registerArgument('section', 'string', 'The anchor to be added to the URI', false, '');
        $this->registerArgument('format', 'string', 'The requested format, e.g. ".html', false, '');
        $this->registerArgument('ajax', 'bool', 'TRUE if the URI should be to an AJAX widget, FALSE otherwise.', false, false);
    }

    /**
     * Render the link.
     *
     * @return string The rendered link
     */
    public function render()
    {
        $ajax = $this->arguments['ajax'];

        if ($ajax === true) {
            $uri = $this->getAjaxUri();
        } else {
            $uri = $this->getWidgetUri();
        }
        $this->tag->addAttribute('href', $uri);
        $this->tag->setContent($this->renderChildren());
        return $this->tag->render();
    }

    /**
     * Get the URI for an AJAX Request.
     *
     * @return string the AJAX URI
     */
    protected function getAjaxUri()
    {
        $action = $this->arguments['action'];
        $arguments = $this->arguments['arguments'];
        if ($action === null) {
            $action = $this->renderingContext->getControllerContext()->getRequest()->getControllerActionName();
        }
        $arguments['id'] = $GLOBALS['TSFE']->id;
        // @todo page type should be configurable
        $arguments['type'] = 7076;
        $arguments['fluid-widget-id'] = $this->renderingContext->getControllerContext()->getRequest()->getWidgetContext()->getAjaxWidgetIdentifier();
        $arguments['action'] = $action;
        return '?' . http_build_query($arguments, null, '&');
    }

    /**
     * Get the URI for a non-AJAX Request.
     *
     * @return string the Widget URI
     */
    protected function getWidgetUri()
    {
        $uriBuilder = $this->renderingContext->getControllerContext()->getUriBuilder();
        $argumentPrefix = $this->renderingContext->getControllerContext()->getRequest()->getArgumentPrefix();
        $arguments = $this->hasArgument('arguments') ? $this->arguments['arguments'] : [];
        if ($this->hasArgument('action')) {
            $arguments['action'] = $this->arguments['action'];
        }
        if ($this->hasArgument('format') && $this->arguments['format'] !== '') {
            $arguments['format'] = $this->arguments['format'];
        }
        return $uriBuilder->reset()
            ->setArguments([$argumentPrefix => $arguments])
            ->setSection($this->arguments['section'])
            ->setUseCacheHash($this->arguments['useCacheHash'])
            ->setAddQueryString(true)
            ->setAddQueryStringMethod($this->arguments['addQueryStringMethod'])
            ->setArgumentsToBeExcludedFromQueryString($this->getArgumentsToBeExcluded())
            ->setFormat($this->arguments['format'])
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
     * @return string the Widget URI
     */
    protected function getArgumentsToBeExcluded()
    {
        // Set plugin prefix
        $pluginPrefix = 'tx_news_pi1';
        // Default arguments to be excluded
        $argumentsToBeExcluded = [
            $this->renderingContext->getControllerContext()->getRequest()->getArgumentPrefix(),
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
