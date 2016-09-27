<?php
  namespace BK2K\BootstrapPackage\ViewHelpers;
  /*
  *  The MIT License (MIT)
  *
  *  Copyright (c) 2014 Benjamin Kott, http://www.bk2k.info
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
  use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
  use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
  use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;
  /**
  * @author Benjamin Kott <info@bk2k.info>
  */

  class CssViewHelper extends AbstractViewHelper implements CompilableInterface
  {

    public function initializeArguments() {
      $this->registerArgument('inline', 'boolean', 'When set, add css to body', FALSE, FALSE);
    }
    /*
    * render
    * @return string
    */
    public function render()
    {
      return self::renderStatic(
        $this->arguments,
        $this->buildRenderChildrenClosure(),
        $this->renderingContext
      );
    }
    /**
    * @param array $arguments
    * @param \Closure $renderChildrenClosure
    * @param RenderingContextInterface $renderingContext
    * @return string
    */
    public static function renderStatic(
      array $arguments,
      \Closure $renderChildrenClosure,
      RenderingContextInterface $renderingContext
    ) {
      $content = '';
      $css = $renderChildrenClosure();
      // put css background to header or leave inline
      if ($settings['inline']){
        $content = "<style>" . $css . "</style>";
        } else {
        $name = uniqid("bootstrap_package_");
        $GLOBALS['TSFE']->getPageRenderer()->addCssInlineBlock($name, $css, true);
        }
      return $content;
    }
  }
