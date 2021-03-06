<?php
namespace BK2K\BootstrapPackage\DataProcessing;

/*
 *  The MIT License (MIT)
 *
 *  Copyright (c) 2015 Benjamin Kott, http://www.bk2k.info
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in
 *  all copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * This menu processor utilizes HMENU to generate a json encoded menu
 * string that will be decoded again and assigned to FLUIDTEMPLATE as
 * variable. Additional DataProcessing is supported and will be applied
 * to each record.
 *
 * Options:
 * as - The variable to be used within the result
 * levels - Number of levels of the menu
 * expandAll = If false, submenus will only render if the parent page is active
 * includeSpacer = If true, pagetype spacer will be included in the menu
 * titleField = Field that should be used for the title
 *
 * See HMENU docs for more options.
 * https://docs.typo3.org/typo3cms/TyposcriptReference/ContentObjects/Hmenu/Index.html
 *
 *
 * Example TypoScript configuration:
 *
 * 10 = BK2K\BootstrapPackage\DataProcessing\MenuProcessor
 * 10 {
 *   special = list
 *   special.value.field = pages
 *   levels = 7
 *   as = menu
 *   expandAll = 1
 *   includeSpacer = 1
 *   titleField = nav_title // title
 *   dataProcessing {
 *    10 = TYPO3\CMS\Frontend\DataProcessing\FilesProcessor
 *    10 {
 *        references.fieldName = media
 *      }
 *    }
 *  }
 *
 */
class MenuProcessor implements DataProcessorInterface
{
    const LINK_PLACEHOLDER = '###LINKPLACEHOLDER###';
    const TARGET_PLACEHOLDER = '###TARGETPLACEHOLDER###';

    /**
     * The content object renderer
     *
     * @var ContentObjectRenderer
     */
    public $cObj;

    /**
     * The processor configuration
     *
     * @var array
     */
    protected $processorConfiguration;

    /**
     * Allowed configuration keys for menu generation, other keys will be
     * ignored to prevent configuration errors.
     *
     * @var array
     */
    public $allowedConfigurationKeys = [
        'cache_period',
        'entryLevel',
        'entryLevel.',
        'special',
        'special.',
        'minItems',
        'minItems.',
        'maxItems',
        'maxItems.',
        'begin',
        'begin.',
        'excludeUidList',
        'excludeUidList.',
        'excludeDoktypes',
        'includeNotInMenu',
        'alwaysActivePIDlist',
        'alwaysActivePIDlist.',
        'protectLvar',
        'if',
        'if.',
    ];

    /**
     * @var array
     */
    protected $menuConfig = [
        'wrap' => '[|]'
    ];

    /**
     * @var array
     */
    protected $menuLevelConfig = [
        'doNotLinkIt' => '1',
        'wrapItemAndSub' => '{|}, |*| {|}, |*| {|}',
        'stdWrap.' => [
            'cObject' => 'COA',
            'cObject.' => [
                '10' => 'USER',
                '10.' => [
                    'userFunc' => 'BK2K\BootstrapPackage\DataProcessing\MenuProcessor->getDataAsJson',
                    'stdWrap.' => [
                        'wrap' => '"data":|'
                    ]
                ],
                '20' => 'TEXT',
                '20.' => [
                    'field' => 'nav_title // title',
                    'trim' => '1',
                    'wrap' => ',"title":|',
                    'preUserFunc' => 'BK2K\BootstrapPackage\DataProcessing\MenuProcessor->jsonEncodeUserFunc'
                ],
                '21' => 'TEXT',
                '21.' => [
                    'value' => MenuProcessor::LINK_PLACEHOLDER,
                    'wrap' => ',"link":|',
                ],
                '22' => 'TEXT',
                '22.' => [
                    'value' => MenuProcessor::TARGET_PLACEHOLDER,
                    'wrap' => ',"target":|',
                ],
                '30' => 'TEXT',
                '30.' => [
                    'value' => '0',
                    'wrap' => ',"active":|'
                ],
                '40' => 'TEXT',
                '40.' => [
                    'value' => '0',
                    'wrap' => ',"current":|'
                ],
                '50' => 'TEXT',
                '50.' => [
                    'value' => '0',
                    'wrap' => ',"spacer":|'
                ]
            ]
        ]
    ];

    /**
     * @var array
     */
    public $menuDefaults = [
        'levels' => 1,
        'expandAll' => 1,
        'includeSpacer' => 0,
        'as' => 'menu',
        'titleField' => 'nav_title // title'
    ];

    /**
     * @var int
     */
    protected $menuLevels;

    /**
     * @var int
     */
    protected $menuExpandAll;

    /**
     * @var int
     */
    protected $menuIncludeSpacer;

    /**
     * @var string
     */
    protected $menuTitleField;

    /**
     * @var string
     */
    protected $menuTargetVariableName;

    /**
     * @var ContentDataProcessor
     */
    protected $contentDataProcessor;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class);
    }

    /**
     * Get configuration value from processorConfiguration
     *
     * @param string $key
     * @return string
     */
    protected function getConfigurationValue($key)
    {
        return $this->cObj->stdWrapValue($key, $this->processorConfiguration, $this->menuDefaults[$key]);
    }

    /**
     * @return array
     */
    public function prepareConfiguration()
    {
        // Filter configuration
        foreach ($this->processorConfiguration as $key => $value) {
            if (in_array($key, $this->allowedConfigurationKeys)) {
                $this->menuConfig[$key] = $value;
            }
        }
        // Process special value
        if (isset($this->menuConfig['special.']['value.'])) {
            $this->menuConfig['special.']['value'] = $this->cObj->stdWrap($this->menuConfig['special.']['value'], $this->menuConfig['special.']['value.']);
            unset($this->menuConfig['special.']['value.']);
        }
        return $this->menuConfig;
    }

    /**
     * @return void
     */
    public function prepareLevelConfiguration()
    {
        $this->menuLevelConfig['stdWrap.']['cObject.'] = array_replace_recursive(
            $this->menuLevelConfig['stdWrap.']['cObject.'],
            [
                '20.' => [
                    'field' => $this->menuTitleField,
                ]
            ]
        );
    }

    /**
     * @return void
     */
    public function prepareLevelLanguageConfiguration()
    {
        if ($this->menuConfig['special'] === 'language') {
            $this->menuLevelConfig['stdWrap.']['cObject.'] = array_replace_recursive(
                $this->menuLevelConfig['stdWrap.']['cObject.'],
                [
                    '60' => 'TEXT',
                    '60.' => [
                        'value' => '1',
                        'wrap' => ',"available":|'
                    ],
                    '70' => 'TEXT',
                    '70.' => [
                        'value' => $this->menuConfig['special.']['value'],
                        'listNum.' => [
                            'stdWrap.' => [
                                'data' => 'register:count_HMENU_MENUOBJ',
                                'wrap' => '|-1'
                            ],
                            'splitChar' => ','
                        ],
                        'wrap' => ',"languageUid":"|"'
                    ]
                ]
            );
        }
    }

    /**
     * @return void
     */
    public function buildConfiguration()
    {
        for ($i = 1; $i <= $this->menuLevels; $i++) {
            $this->menuConfig[$i] = 'TMENU';
            $this->menuConfig[$i . '.']['IProcFunc'] = 'BK2K\BootstrapPackage\DataProcessing\MenuProcessor->replacePlaceholderInRenderedMenuItem';
            if ($i > 1) {
                $this->menuConfig[$i . '.']['stdWrap.']['wrap'] = ',"children": [|]';
            }
            $this->menuConfig[$i . '.']['expAll'] = $this->menuExpandAll;
            $this->menuConfig[$i . '.']['NO'] = '1';
            $this->menuConfig[$i . '.']['NO.'] = $this->menuLevelConfig;
            if ($this->menuIncludeSpacer) {
                $this->menuConfig[$i . '.']['SPC'] = '1';
                $this->menuConfig[$i . '.']['SPC.'] = $this->menuConfig[$i . '.']['NO.'];
                $this->menuConfig[$i . '.']['SPC.']['stdWrap.']['cObject.']['50.']['value'] = '1';
            }
            $this->menuConfig[$i . '.']['IFSUB'] = '1';
            $this->menuConfig[$i . '.']['IFSUB.'] = $this->menuConfig[$i . '.']['NO.'];
            $this->menuConfig[$i . '.']['ACT'] = '1';
            $this->menuConfig[$i . '.']['ACT.'] = $this->menuConfig[$i . '.']['NO.'];
            $this->menuConfig[$i . '.']['ACT.']['stdWrap.']['cObject.']['30.']['value'] = '1';
            $this->menuConfig[$i . '.']['ACTIFSUB'] = '1';
            $this->menuConfig[$i . '.']['ACTIFSUB.'] = $this->menuConfig[$i . '.']['ACT.'];
            $this->menuConfig[$i . '.']['CUR'] = '1';
            $this->menuConfig[$i . '.']['CUR.'] = $this->menuConfig[$i . '.']['ACT.'];
            $this->menuConfig[$i . '.']['CUR.']['stdWrap.']['cObject.']['40.']['value'] = '1';
            $this->menuConfig[$i . '.']['CURIFSUB'] = '1';
            $this->menuConfig[$i . '.']['CURIFSUB.'] = $this->menuConfig[$i . '.']['CUR.'];
            if ($this->menuConfig['special'] === 'language') {
                $this->menuConfig[$i . '.']['USERDEF1'] = $this->menuConfig[$i . '.']['NO'];
                $this->menuConfig[$i . '.']['USERDEF1.'] = $this->menuConfig[$i . '.']['NO.'];
                $this->menuConfig[$i . '.']['USERDEF1.']['stdWrap.']['cObject.']['60.']['value'] = '0';
                $this->menuConfig[$i . '.']['USERDEF2'] = $this->menuConfig[$i . '.']['ACT'];
                $this->menuConfig[$i . '.']['USERDEF2.'] = $this->menuConfig[$i . '.']['ACT.'];
                $this->menuConfig[$i . '.']['USERDEF2.']['stdWrap.']['cObject.']['60.']['value'] = '0';
            }
        }
    }

    /**
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        $this->cObj = $cObj;
        $this->processorConfiguration = $processorConfiguration;

        // Get Configuration
        $this->menuLevels = (int)$this->getConfigurationValue('levels') ?: 1;
        $this->menuExpandAll = (int)$this->getConfigurationValue('expandAll');
        $this->menuIncludeSpacer = (int)$this->getConfigurationValue('includeSpacer');
        $this->menuTargetVariableName = $this->getConfigurationValue('as');
        $this->menuTitleField = $this->getConfigurationValue('titleField');

        // Build Configuration
        $this->prepareConfiguration();
        $this->prepareLevelConfiguration();
        $this->prepareLevelLanguageConfiguration();
        $this->buildConfiguration();

        // Process Configuration
        $menuContentObject = $cObj->getContentObject('HMENU');
        $renderedMenu = $menuContentObject->render($this->menuConfig);
        if (!$renderedMenu) {
            return $processedData;
        }

        // Process menu
        $menu = json_decode($renderedMenu, true);
        $processedMenu = array();

        foreach ($menu as $key => $page) {
            $processedMenu[$key] = $this->processAdditionalDataProcessors($page, $processorConfiguration);
        }

        // Return processed data
        $processedData[$this->menuTargetVariableName] = $processedMenu;
        return $processedData;
    }

    /**
     * Process additional data processors
     *
     * @param array $page
     * @param array $processorConfiguration
     */
    protected function processAdditionalDataProcessors($page, $processorConfiguration)
    {
        if (is_array($page['children'])) {
            foreach ($page['children'] as $key => $item) {
                $page['children'][$key] = $this->processAdditionalDataProcessors($item, $processorConfiguration);
            }
        }
        /** @var ContentObjectRenderer $recordContentObjectRenderer */
        $recordContentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $recordContentObjectRenderer->start($page['data'], 'pages');
        $processedPage = $this->contentDataProcessor->process($recordContentObjectRenderer, $processorConfiguration, $page);
        return $processedPage;
    }

    /**
     * Gets the data of the current record in JSON format
     *
     * @return string JSON encoded data
     */
    public function getDataAsJson()
    {
        return $this->jsonEncode($this->cObj->data);
    }

    /**
     * This UserFunc encodes the content as Json
     *
     * @param string $content
     * @param array $conf
     * @return string JSON encoded content
     */
    public function jsonEncodeUserFunc($content, $conf)
    {
        $content = $this->jsonEncode($content);
        return $content;
    }

    /**
     * JSON Encode
     *
     * @param mixed $value
     * @return string
     */
    public function jsonEncode($value)
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }

    /**
     * This UserFunc gets the link and the target
     *
     * @param array $menuItem
     * @param array $conf
     * @return void
     */
    public function replacePlaceholderInRenderedMenuItem($menuItem, $conf)
    {
        $link = $this->jsonEncode($menuItem['linkHREF']['HREF']);
        $target = $this->jsonEncode($menuItem['linkHREF']['TARGET']);

        $menuItem['parts']['title'] = str_replace(MenuProcessor::LINK_PLACEHOLDER, $link, $menuItem['parts']['title']);
        $menuItem['parts']['title'] = str_replace(MenuProcessor::TARGET_PLACEHOLDER, $target, $menuItem['parts']['title']);

        return $menuItem;
    }
}
