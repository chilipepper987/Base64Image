<?php

/**
 * This is a helper class for dealing with base64 image data. It can convert base64 image strings to binary
 * It was written to help convert submitted html data with data-uri images into binary data serverside
 */

namespace cnatp\Base64Image;

class Base64Image {

    private $prefix, $data, $imgType;

    public function setData($data) {
        $this->prefix = substr($data, 0, 14);
        if ($this->isDataImage()) {
            //data:image/png;base64,iVBORw0KGgoAAAAN...
            list($type, $data) = explode(';', $data);
            list(, $data) = explode(',', $data);
            list(, $imgType) = explode("/", $type);
            $this->data = $data;
            $this->imgType = $imgType;
        }
    }

    /**
     * True if the src is a data string, false if otherwise
     * @return boolean
     */
    public function isDataImage() {
        return strpos($this->prefix, 'data:image') !== false;
    }

    /**
     * Returns the equivalent binary image string of the base64 data if its a valid base64 image, otherwise return false
     * @return boolean|string
     */
    public function toBinaryImage() {
        if (!$this->isDataImage()) {
            return false;
        }
        //so we have an image
        $binary = base64_decode($this->data);
        return $binary;
    }

    /**
     * Writes the image with <code>$filename</code> to <code>$path</code> if its
     * a valid base64 image. Otherwise will throw an exception without writing anything.
     * @param string $path
     * @param string $fileName
     * @return string|Exception The fully qualified file name, or throws an exception
     * @throws Exception
     */
    public function writeToDisk($path, $fileName = 'image') {
        if (is_writable($path) && $this->isDataImage()) {
            $path = str_replace("//", "/", $path . "/");
            if (file_put_contents($path . $fileName . "." . $this->imgType, $this->toBinaryImage())) {
                //successful write
                return $fileName . "." . $this->imgType;
            } else {
                //write failed. if the path is writable this shouldn't ever happen
                throw new Exception("Failed to write image even though path ($path) is writable and the file is (allegedly) a valid data image.");
            }
        } else {
            //permission denied or not a data image
            if (is_writable($path)) {
                $msg = "The file is not a valid data image.";
            } else {
                $msg = "The path ($path) is not writable. This is most likely a permissions issue.";
            }
            throw new Exception($msg);
        }
    }

    /**
     * Given an HTML string, this will look for any img tags referencing data images, convert them to actual images, write them to disk,
     * then update the img tags to reference the newly created files. Returns back the HTML string.
     * @param string $html HTML string for MCE editor
     * @param string $diskPath where the files should be written
     * @param string $urlPath http readable directory of the diskpath
     * @return string The HTML
     */
    public static function convertDataURIs($html, $diskPath, $urlPath) {
        //add a trailing slash to paths if not already present
        $diskPath = $diskPath . (substr($diskPath, -1) === "/" ? "" : "/");
        $urlPath = $urlPath . (substr($urlPath, -1) === "/" ? "" : "/");
        $base64Helper = new self;
        //new document
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        //grab img elements
        $imgTags = $doc->getElementsByTagName('img');
        foreach ($imgTags as $img) {
            $src = $img->getAttribute('src');
            $base64Helper->setData($src);
            if ($base64Helper->isDataImage()) {
                //then write out the file and get the path
                $filename = $base64Helper->writeToDisk($diskPath, uniqid());
                if ($filename) {
                    // swap the src of the dom element with the new file we just wrote
                    $newSrc = $urlPath . $filename;
                    $img->setAttribute('src', $newSrc);
                    $html = str_replace($src, $newSrc, $html);
                } //no else, all exceptions will be caught
            } // else not a data image. so don't do anything
        }
        //now dump the dom html string back into the variable
        return $html;
    }

}
