<?php

namespace Cita\ImageCropper\Model;

use SilverStripe\Assets\FileFinder;
use SilverStripe\Control\Director;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\Image;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\LiteralField;
use Leochenftw\Debugger;

/**
 * Description
 *
 * @package silverstripe
 * @subpackage mysite
 */

class CitaCroppableImage extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'CitaCroppableImage';

    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'ContainerX'        =>  'Int',
        'ContainerY'        =>  'Int',
        'ContainerWidth'    =>  'Int',
        'ContainerHeight'   =>  'Int',
        'CropperX'          =>  'Int',
        'CropperY'          =>  'Int',
        'CropperWidth'      =>  'Int',
        'CropperHeight'     =>  'Int'
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'Original'          =>  Image::class
    ];

    private static $extensions = [
        Versioned::class,
    ];

    public function URL()
    {
        return $this->getURL();
    }

    public function getURL()
    {
        $image              =   $this->Cropped() ? $this->Cropped() : $this->Original();
        return $image->getURL();
    }

    public function getThumbnail()
    {
        return  $this->Cropped() ?
                $this->Cropped()->FitMax(200, 200) :
                (
                    $this->Original()->exists() ?
                    $this->Original()->FitMax(200, 200) :
                    null
                );
    }

    public function Cropped()
    {
        return $this->getCropped();
    }

    public function getCropped()
    {
        if (!$this->Original()->exists()) {
            return null;
        }


        if (empty($this->CropperWidth) && empty($this->CropperHeight)) {
            return $this->Original();
        }

        $canvas_x           =   $this->ContainerX;
        $canvas_y           =   $this->ContainerY;
        $canvas_w           =   $this->ContainerWidth;
        $canvas_h           =   $this->ContainerHeight;
        $cropper_x          =   $this->CropperX;
        $cropper_y          =   $this->CropperY;
        $cropper_w          =   $this->CropperWidth;
        $cropper_h          =   $this->CropperHeight;
        $original_width     =   $this->Original()->getWidth();

        $x                  =   $cropper_x + $canvas_x;
        $y                  =   $cropper_y + $canvas_y;

        if ($original_width != $canvas_w) {
            $ratio = $original_width / $canvas_w;

            $cropper_w = $cropper_w * $ratio;
            $cropper_h = $cropper_h * $ratio;
            $x = $x * $ratio;
            $y = $y * $ratio;
        }

        $y                  =   round($y);
        $x                  =   round($x);
        $cropper_w          =   round($cropper_w);
        $cropper_h          =   round($cropper_h);

        return $this->Original()->crop($x, $y, $cropper_w, $cropper_h);
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        Requirements::css('haunt-jonathan/image-cropper: client/js/cropperjs/dist/cropper.min.css');
        Requirements::javascript('haunt-jonathan/image-cropper: client/js/cropperjs/dist/cropper.min.js');
        Requirements::javascript('haunt-jonathan/image-cropper: client/js/cita-cropper.js');

        $fields = parent::getCMSFields();
        $fields->removeByName([
            'Cropped',
            'ContainerX',
            'ContainerY',
            'ContainerWidth',
            'ContainerHeight',
            'CropperX',
            'CropperY',
            'CropperWidth',
            'CropperHeight'
        ]);

        $fields->addFieldsToTab(
            'Root.Main',
            [
                UploadField::create(
                    'Original',
                    'Original image'
                )
            ]
        );

        if ($this->Original()->exists()) {

            $name           =   'cita-cropper-' . ($this->exists() ? $this->ID : 'new');
            $width          =   $this->Original()->Width;
            $height         =   $this->Original()->Height;
            $ratio          =   $width > 666 ? (666 / $width) : 1;
            $calc_width     =   $width * $ratio;
            $calc_height    =   $height * $ratio;
            $styles         =   " style=\"width:{$calc_width}px; height:{$calc_height}px\"";

            $html           =   '<div class="cita-cropper"'. $styles .' data-name="'.$name.'" data-min-width="'. $calc_width .'" data-min-height="' . $calc_height . '"><img src="'.$this->Original()->URL.'?timestamp=' . time() . '" width="'.$width.'" height="'.$height.'" /></div>';

            $fields->push(LiteralField::create('CitaCropper', $html));
        }

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     * Event handler called after writing to the database.
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->Original()->publish('Stage', 'Live');
    }

    public function forTemplate()
    {
        return $this->renderWith([CitaCroppableImage::class]);
    }

    public function getData($resample = 'ScaleWidth', $width = 600, $height = null)
    {
        if (!$this->exists()) return null;
        if (!$this->getCropped()) return null;
        if (!$this->getCropped()->Width || !$this->getCropped()->Height) return null;

        // if is array, the [0] = phone, [1] = tablet, [2] = desktop
        if (is_array($width)) {
            $base_data  =   null;

            if (!empty($width) && !empty($height)) {
                $base_data  =   $this->get_base_data($resample, $width[count($width) - 1], $height[count($height) - 1]);
                if (count($width) > 0) {
                    $base_data['small'] =  $this->getCropped()->$resample($width[0] * 2, $height[0] * 2)->getAbsoluteURL();
                }

                if (count($width) > 1) {
                    $base_data['medium'] =  $this->getCropped()->$resample($width[1] * 2, $height[1] * 2)->getAbsoluteURL();
                }

                if (count($width) > 2) {
                    $base_data['large'] =   $base_data['url'];
                }

            } elseif (empty($width) && !empty($height)) {
                $base_data  =   $this->get_base_data($resample, null, $height[count($height) - 1]);
                if (count($width) > 0) {
                    $base_data['small'] =   $this->getCropped()->$resample($height[0] * 2)->getAbsoluteURL();
                }

                if (count($width) > 1) {
                    $base_data['medium'] =   $this->getCropped()->$resample($height[1] * 2)->getAbsoluteURL();
                }

                if (count($width) > 2) {
                    $base_data['large'] =   $base_data['url'];
                }

            } else {
                $base_data  =   $this->get_base_data($resample, $width[count($width) - 1]);
                if (count($width) > 0) {
                    $base_data['small'] =   $this->getCropped()->$resample($width[0] * 2)->getAbsoluteURL();
                }

                if (count($width) > 1) {
                    $base_data['medium'] =   $this->getCropped()->$resample($width[1] * 2)->getAbsoluteURL();
                }

                if (count($width) > 2) {
                    $base_data['large'] =   $base_data['url'];
                }

            }

            return $base_data;
        }

        return $this->get_base_data($resample, $width, $height);
    }

    private function get_base_data($resample = 'ScaleWidth', $width = 600, $height = null)
    {
        $re_height  =   !empty($height) ? $height : round($this->get_ratio($this->getCropped()->Width, $width) * $this->getCropped()->Height);
        return [
            'id'        =>  $this->ID,
            'title'     =>  $this->Title,
            'ratio'     =>  round((empty($height) ? ($this->getCropped()->Height / $this->getCropped()->Width) : ($height / $width)) * 10000) / 100,
            'url'       =>  empty($height) ?
                            $this->getCropped()->$resample($width * 2)->getAbsoluteURL() :
                            (empty($width) ? $this->getCropped()->$resample($height * 2)->getAbsoluteURL() :
                            $this->getCropped()->$resample($width * 2, $height * 2)->getAbsoluteURL()),
            'width'     =>  $width,
            'height'    =>  $re_height
        ];
    }

    private function get_ratio($original, $target = 800)
    {
        if (empty($original)) return 1;
        return $target/$original;
    }
}
