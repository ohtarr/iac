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

class Vmspec
{
    public $hostname;
    public $network;
    public $userdata;
    public $template;
    public $vmspec;

    public function __construct($hostname, $network, $pubkey ,$userdata)
    {
        $this->hostname = $hostname;
        $this->network = $network;
        $this->pubkey = $pubkey;
        $this->userdata = $userdata;
    }

/*     public function getTemplate($json)
    {
        $object = json_decode($json);
        $this->template = $object;
        return $object;
    } */

    public static function getTemplateFile($file)
    {
        $json = file_get_contents($file);
        $object = json_decode($json);
        //$this->template = $object;
        return $object;
    }

    public function generateVmSpec($file)
    {
        $object = self::getTemplateFile($file);

        $base64 = base64_encode($this->userdata);

        $object->PropertyMapping[1]->Value = $this->hostname;
        $object->PropertyMapping[3]->Value = $this->pubkey;
        $object->Name = $this->hostname;
        $object->NetworkMapping[0]->Network = $this->network;

        $object->PropertyMapping[4]->Value = $base64;

        $this->vmspec = $object;
        return $object;
    }

    public function generateVmSpecFile($file)
    {
        $object = $this->generateVmSpec();
        $json = json_encode($object);
        file_put_contents($file,$json);
        return $json;
    }

}