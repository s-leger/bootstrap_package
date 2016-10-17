<?php
namespace BK2K\BootstrapPackage\ViewHelpers;

/*
*  The MIT License (MIT)
*
*  Copyright (c) 2016 Stephen Leger
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
* @author Stephen Leger
*/

class ImageSizeViewHelper extends AbstractViewHelper implements CompilableInterface
{

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument("as", "string", "Variable name used to store image sizes", false, "imagesize");
        $this->registerArgument("ratio", "float", "Ratio height/width in %, will crop height when > 0", false, 0);
        $this->registerArgument("crop", "string", "vertical crop position in % 0 = center -100 = top 100 = bottom", false, "");
        $this->registerArgument("xs", "float", "Number of columns for extra small", false, 12);
        $this->registerArgument("sm", "float", "Number of columns for small", false, 0);
        $this->registerArgument("md", "float", "Number of columns for medium", false, 0);
        $this->registerArgument("lg", "float", "Number of columns for large", false, 0);
        $this->registerArgument("border", "integer", "Border size of each image", false, 0);
        $this->registerArgument("marginxs", "integer", "Margin for extra small", false, 0);
        $this->registerArgument("marginsm", "integer", "Margin for small", false, 0);
        $this->registerArgument("marginmd", "integer", "Margin for medium", false, 0);
        $this->registerArgument("marginlg", "integer", "Margin for large", false, 0);
        $this->registerArgument("imagewidth", "float", "User defined width", false, 0);
        $this->registerArgument("imageheight", "float", "User defined height", false, 0);
        $this->registerArgument("file", "object", "file properties", false, null);
    }

    /**
    * @return string the rendered string
    * @api
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
    * Backups "width" state of a possible parent imageWidth ViewHelper to support nesting
    *
    * @return array $width
    */
    protected static function backupSizeState(RenderingContextInterface $renderingContext, $settings, $as)
    {

        if ($renderingContext->getTemplateVariableContainer()->exists($as)) {
          
            $size = $renderingContext->getTemplateVariableContainer()->get($as);
            $renderingContext->getTemplateVariableContainer()->remove($as);
          
        } elseif (isset($GLOBALS["TSFE"]->register["template_size"])) {
          
            $size = $GLOBALS["TSFE"]->register["template_size"];
          
        } else {

            // assume fixed layout (xs always fluid) width => container width
            $container = $settings["grid."]["container."];
            $fluid = $settings["grid."]["fluid."];

            // fluid width => next container width
            $size = array (
                "width"  => array(
                    "xxs" => $container["xs"], // container xs
                    "xs" => (($fluid["xs"]) ? $container["sm"] : $container["xs"]),  // container sm
                    "sm" => (($fluid["sm"]) ? $container["md"] : $container["sm"]),  // container md
                    "md" => (($fluid["md"]) ? $container["lg"] : $container["md"]),  // container lg
                    "lg" => (($fluid["lg"]) ? $container["xl"] : $container["lg"])  // container xl
                ),
                "ratio" => 0,
                "height"  => array(
                    "xxs" => "",
                    "xs" => "",
                    "sm" => "",
                    "md" => "",
                    "lg" => ""
                ),
                "cols" => array(
                    "xs" => 1,
                    "sm" => 1,
                    "md" => 1,
                    "lg" => 1
                )
            );
        }
        return $size;
    }

    /**
    * Restore "width" state  that might have been backed up in backupWidthState() before
    *
    * @return void
    */
    protected static function restoreSizeState(array $size, RenderingContextInterface $renderingContext, $as)
    {
        $renderingContext->getTemplateVariableContainer()->remove($as);
        $renderingContext->getTemplateVariableContainer()->add($as, $size);
    }

    private static function getSettings()
    {
        return $GLOBALS["TSFE"]->tmpl->setup["plugin."]["bootstrap_package."]["settings."];
    }

    /**
    * @param array $arguments
    * @param \Closure $renderChildrenClosure
    * @param RenderingContextInterface $renderingContext
    * @return string $content
    */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {

        $settings = self::getSettings();

        $as = $arguments["as"];

        $xs = $arguments["xs"];
        $sm = $arguments["sm"];
        $md = $arguments["md"];
        $lg = $arguments["lg"];

        if ($sm == 0) {
            $sm = $xs;
        }
       
        if ($md == 0) {
            $md = $sm;
        }
        
        if ($lg == 0) {
            $lg = $md;
        }

        $marginxs = 2*$arguments["marginxs"];
        $marginsm = 2*$arguments["marginsm"];
        $marginmd = 2*$arguments["marginmd"];
        $marginlg = 2*$arguments["marginlg"];
        
        if ($marginsm == 0) {
            $marginsm = $marginxs;
        }

        if ($marginmd == 0) {
            $marginmd = $marginsm;
        }

        if ($marginlg == 0) {
            $marginlg = $marginmd;
        }
      
        $border = 2*$arguments["border"];


      
        
      
        // user defined image ratio, will crop either width or height
        // require file argument to work correctly
        if (1*$arguments["ratio"] > 0) {
            $ratio = 1*$arguments["ratio"] / 100;
        } elseif (isset($size["ratio"])) {
            $ratio = $size["ratio"];
        } else {
            $ratio = 0;
        }
      
        // specify the crop in percent : -100 = left or top 100 = right or bottom
        // eg for download files thumbs 
        if ($arguments["crop"] !== '') {
            $crop = $arguments["crop"];
        } elseif (isset($size["crop"])) {
            $crop = $size["crop"];
        } else {
            $crop = '';
        }
      
        // user defined image size, will override all other settings
        if (1*$arguments['imagewidth'] > 0){
           $imagewidth = 1*$arguments['imagewidth'];
        } elseif (isset($size['imagewidth'])) {
           $imagewidth = $size['imagewidth'];   
        } else {
           $imagewidth = 0;
        }
      
        if (1*$arguments['imageheight'] > 0){
           $imageheight = 1*$arguments['imageheight'];
        } elseif (isset($size['imageheight'])) {
           $imageheight = $size['imageheight'];   
        } else {
           $imageheight = 0;
        }
        
        if ($imageheight > 0 and $imagewidth > 0){
            $ratio = $imageheight / $imagewidth;
        }
      
        // retrieve real file size if any
        if (isset($size['fileratio'])) {
            $fileratio = $size['fileratio'];
        } elseif ($arguments['file'] !== null) {
            $fheight= $arguments['file']->getProperty('height');
            $fwidth = $arguments['file']->getProperty('width');
            $fcrop =  $arguments['file']->getProperty('crop');
            if ($fcrop) {
                $fcrop = json_decode($fcrop);
                $fwidth = $fcrop->width;
                $fheight= $fcrop->height;
            }
            $fileratio = $fheight / $fwidth;
        } else {
            $fileratio = 500;
        }
        
      
        $gutter  = $settings["grid."]["gutter"];
        $maxcols = $settings["grid."]["columns"];

        // automatic size (containers)
        $size = self::backupSizeState($renderingContext, $config, $as);
        
        // user defined size
        if ($imagewidth > 0 or $imageheight > 0) {
            // width proportional to container
            $refwidth =  $imagewidth / $settings['grid.']['container.']['lg'];
            $width = array(
                'xxs' => $refwidth * $settings['grid.']['container.']['xs'],
                'xs' => $refwidth * $settings['grid.']['container.']['xs'],
                'sm' => $refwidth * $settings['grid.']['container.']['sm'],
                'md' => $refwidth * $settings['grid.']['container.']['md'],
                'lg' => $imagewidth
            );
           
        } else {
          
            $width = array(
                "xxs" => ($size["width"]["xxs"] + $gutter - $marginxs) / $maxcols * $xs - $gutter - $border,
                "xs" => ($size["width"]["xs"] + $gutter - $marginxs) / $maxcols * $xs - $gutter - $border,
                "sm" => ($size["width"]["sm"] + $gutter - $marginsm) / $maxcols * $sm - $gutter - $border,
                "md" => ($size["width"]["md"] + $gutter - $marginmd) / $maxcols * $md - $gutter - $border,
                "lg" => ($size["width"]["lg"] + $gutter - $marginlg) / $maxcols * $lg - $gutter - $border
            );
        
        }

        
      
        if ($ratio > 0) {
          
          $height = array(
                "xxs" => ($width["xxs"] * $ratio),
                "xs" =>  ($width["xs"] * $ratio),
                "sm" => ($width["sm"] * $ratio),
                "md" => ($width["md"] * $ratio),
                "lg" => ($width["lg"] * $ratio)
          );
          
          // crop width if ratio > image ratio
          if ($fileratio > $ratio) {
            
            $height["xxs"] .= "c" . $crop;
            $height["xs"] .= "c" . $crop; 
            $height["sm"] .= "c" . $crop;
            $height["md"] .= "c" . $crop; 
            $height["lg"] .= "c" . $crop;
            
          } else {
            
            $width["xxs"] .= "c" . $crop;
            $width["xs"] .= "c" . $crop; 
            $width["sm"] .= "c" . $crop;
            $width["md"] .= "c" . $crop; 
            $width["lg"] .= "c" . $crop;
           
          }
          
        } else {
          
            $height = array(
                "xxs" => "",
                "xs" => "",
                "sm" => "",
                "md" => "",
                "lg" => ""
            );
          
        }

        $newSize = array (
            "imageheight" => $imageheight,
            "imagewidth" => $imagewidth,
            "width" => $width,
            "height" => $height,
            "ratio" => $ratio,
            "fileratio" => $fileratio,
            "crop" => $crop,
            "cols" => array(
                "xs" => $maxcols/$xs,
                "sm" => $maxcols/$sm,
                "md" => $maxcols/$md,
                "lg" => $maxcols/$lg
            )
        );

        $renderingContext->getTemplateVariableContainer()->add($as, $newSize);

        $content = $renderChildrenClosure();
        // restore only if the tag is closed and has childrens

        if ($content != "") {
            self::restoreSizeState($size, $renderingContext, $as);
        }

        return $content;
    }
}
