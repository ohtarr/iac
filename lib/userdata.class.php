<?php

/**
 * deploy.class.php.
 *
 *
 *
 * PHP version 7.2
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3.0 of the License, or (at your option) any later version.
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category  default
 *
 * @author    Andrew Jones
 * @copyright 2021 @authors
 * @license   http://www.gnu.org/copyleft/lesser.html The GNU LESSER GENERAL PUBLIC LICENSE, Version 3.0
 */

namespace Ohtarr;

class Userdata
{

    public $userdatain;
    public $userdataout;
    public $templates;

    public function __construct()
    {

    }

    public function addUserdata($string)
    {
        $this->userdatain .= $string;
    }

    public function getTemplatesFromFolder($folder)
    {
        if(!is_dir($folder))
        {
            return;
        }
        $templates = scandir($folder);
        array_shift($templates);
        array_shift($templates);
        foreach($templates as $template)
        {
            $string = file_get_contents($folder . "/" . $template);
            $array[$template] = $string;
        }
        $this->templates = $array;
        return $array;
    }

    public function generateCloudInit()
    {
        $output = "";
        $reg = "/function\s+(\S+)\s+{/";
        foreach($this->templates as $key => $value)
        {
            if(preg_match($reg,$value,$hits))
            {
                $namereg = "/^\s*" . $hits[1] . "/m";
                if(preg_match($namereg,$this->userdatain,$hits2))
                {
                    $output .= $value;
                    //file_put_contents($file,$value, FILE_APPEND);
                }
            }
        }
        $output .= $this->userdatain;
        $this->userdataout = $output;
        return $output;
    }

    public function generateCloudInitFile($file)
    {
        $output = $this->generateCloudInit();
        file_put_contents($file,$output);
        return $output;
    }

}