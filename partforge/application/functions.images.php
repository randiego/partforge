<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2015 Randall C. Black <randy@blacksdesign.com>
 *
 * This file is part of PartForge
 *
 * PartForge is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * PartForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PartForge.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
 */

// rewritten from http://us2.php.net/getimagesize
function makeCommensuratelyConstrainedImage($o_file, $max_ht = 200, $max_wd = 800, &$out_type) {
   
   $image_info = getImageSize($o_file) ; // see EXIF for faster way
  
   $out_type = '';
   switch ($image_info['mime']) {
       case 'image/gif':
           if (imagetypes() & IMG_GIF)  { // not the same as IMAGETYPE
               $o_im = imageCreateFromGIF($o_file) ;
           } else {
               $ermsg = 'GIF images are not supported<br />';
           }
           $out_type = 'image/gif';
           break;
       case 'image/jpeg':
           if (imagetypes() & IMG_JPG)  {
               $o_im = imageCreateFromJPEG($o_file) ;
           } else {
               $ermsg = 'JPEG images are not supported<br />';
           }
           $out_type = 'image/jpeg';
           break;
       case 'image/png':
           if (imagetypes() & IMG_PNG)  {
               $o_im = imageCreateFromPNG($o_file) ;
           } else {
               $ermsg = 'PNG images are not supported<br />';
           }
           $out_type = 'image/gif';
           break;
       case 'image/wbmp':
           if (imagetypes() & IMG_WBMP)  {
               $o_im = imageCreateFromWBMP($o_file) ;
           } else {
               $ermsg = 'WBMP images are not supported<br />';
           }
           $out_type = 'image/gif';
           break;
       default:
           $ermsg = $image_info['mime'].' images are not supported<br />';
           break;
   }
  
   if (!isset($ermsg)) {
       $o_wd = imagesx($o_im) ;
       $o_ht = imagesy($o_im) ;
       
       $ideal_scale = ($o_wd/$max_wd > $o_ht/$max_ht) ? $max_wd/$o_wd : $max_ht/$o_ht;
       if ($ideal_scale < 1) {
         for ($scale=0.5; $scale > $ideal_scale; $scale = $scale / 2);
       } else {
         $scale = 1;
       }
       
       $t_wd = round($o_wd * $scale);
       $t_ht = round($o_ht * $scale);

       $t_im = imageCreateTrueColor($t_wd,$t_ht);
      
       imageCopyResampled($t_im, $o_im, 0, 0, 0, 0, $t_wd, $t_ht, $o_wd, $o_ht);
      
      ob_start();
      if ($out_type == 'image/gif') {
         imageGIF($t_im);
      } else if ($out_type == 'image/jpeg') {
         imageJPEG($t_im);
      }
      $content = ob_get_clean();
      
       imageDestroy($o_im);
       imageDestroy($t_im);
   }
   echo $ermsg;
   return $content;
}


?>