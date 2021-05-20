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

//use Ohtarr\Govc;

class Server
{
    public $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

/*     public function addUserdata($string)
    {
        $this->cloudinit .= $string;
    } */

    public function getTemplates()
    {
        $folder = $this->params['env']['templates_folder'] . "/" . $this->params['osversion'];
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
        return $array;
    }

    public function generateUserdata()
    {
        $cloudinit = "";
        $cloudinit .= file_get_contents($this->params['servercloudinit']);
        foreach($this->params['apps'] as $app)
        {
            $string = file_get_contents($app);
            $cloudinit .= $string;
        }

        $reg = "/function\s+(\S+)\s+{/";
        $functions = "";
        foreach($this->getTemplates() as $key => $value)
        {
            if(preg_match($reg,$value,$hits))
            {
                $namereg = "/^\s*" . $hits[1] . "/m";
                if(preg_match($namereg,$cloudinit,$hits2))
                {
                    $functions .= $value;
                }
            }
        }
        $userdata = '#!/usr/bin/env bash';
        $userdata .= $functions;
        $userdata .= $cloudinit;
        return $userdata;
    }

    public function generateVmspec()
    {
        $json = file_get_contents($this->params['env']['vmspec_template_file']);
        $object = json_decode($json);
        $base64 = base64_encode($this->generateUserdata());

        $object->PropertyMapping[1]->Value = $this->params['hostname'];
        $object->PropertyMapping[3]->Value = $this->params['env']['pubkey'];
        $object->Name = $this->params['hostname'];
        $object->NetworkMapping[0]->Network = $this->params['network'];

        $object->PropertyMapping[4]->Value = $base64;
        $json = json_encode($object);
        file_put_contents($this->params['vmspecs'],$json);
        return $json;
    }

    public function deployToVmware()
    {
        $this->generateVmspec();
        $govc = new Govc($this->params['vmware_host'], $this->params['env']['vmware_username'], $this->params['env']['vmware_password'], $this->params['dc'], $this->params['hostname']);
        if(!$govc->vmExists()){
            print "VM does not already exist... deploying OVA...\n";
            //Deploy OVA!
            $govc->deployVm($this->params['vmspecs'],$this->params['datastore'],$this->params['pool'],$this->params['env']['os'][$this->params['osversion']]['ova']);
        } else {
            print "VM Already exists! cancelling...\n";
            exit();
        }
        /* if(file_exists($this->params['vmspecs'])){
            print "Deleting json ova template : {$this->params['vmspecs']}\n";
            unlink($this->params['vmspecs']);
        } */
        $timezone = date_default_timezone_get();
        $date = date('m/d/Y h:i:s a', time());
        $note = $this->params['notes'] . " Deployed: {$date} {$timezone}";
        print "Applying VM note: {$note}\n";
        $govc->modifyNotes($note);
        print "Modifying resources to {$this->params['cores']} cpus and {$this->params['ram']} gigs of RAM...\n";
        $govc->modifyResources($this->params['cores'],$this->params['ram']);
        print "Modifying disk size to {$this->params['disksize']} Gigabytes...\n";
        $govc->modifyDiskSize($this->params['disksize']);
        if(isset($this->params['persistant_disks']))
        {
            foreach($this->params['persistant_disks'] as $datastore => $disk)
            {
                print "Mounting persistant disk {$disk}...\n";
                $govc->attachDisk($datastore, $disk);
            }
        }
        print "Deleting all FLOPPY drives...\n";
        $govc->deleteAllFloppies();
        if(isset($this->params['mac']))
        {
            print "Assigning custom MAC ADDRESS: {$this->params['mac']}...\n";
            $govc->networkChangeMac($this->params['network'],$this->params['mac']);
        }
        print "Powering on VM {$this->params['hostname']}...\n";
        $govc->powerOn();
        print "DEPLOY COMPLETE!\n";
    }

    public function destroyVm()
    {
        $govc = new Govc($this->params['vmware_host'], $this->params['env']['vmware_username'], $this->params['env']['vmware_password'], $this->params['dc'], $this->params['hostname']);
        if(!$govc->vmExists())
        {
            exit("VM does not exist!");
        }
        if(isset($this->params['persistant_disks']))
        {
            foreach($this->params['persistant_disks'] as $datastore => $disk)
            {
        
                $diskattached = 1;
                while($diskattached == 1)
                {
                    if($govc->findDisk($disk))
                    {
                        print "Disk {$disk} is attached!\n";
                        print "Detaching disk {$disk}...\n";
                        $govc->detachDisk($disk);
                        sleep(5);
                    } else {
                        print "Disk " . $disk . " is now detached!\n";
                        $diskattached = 0;
                    }
                }
            }
        }
        if($govc->getVmPower())
        {
            print "Vm is powered on.  Shutting down Guest OS\n";
            $govc->shutdownGuest();

            $power=1;
            $totaltime = 0;

            while($power==1)
            {
                if($totaltime >= 120)
                {
                    break;
                }
                if($govc->getVmPower())
                {
                    print "Device is still powered on, checking again momentarily...\n";
                    $totaltime = $totaltime+2;
                    sleep(2);
                } else {
                    print "Device is powered off now.\n";
                    $power = 0;
                }
            }
        } else {
            print "Vm {$govc->hostname} is not powered on currently.  proceeding.\n";
        }
        print "Destroying VM!\n";
        $govc->destroyVm();

        if(!$govc->vmExists())
        {
            print "VM " . $this->params['hostname'] . " no longer exists.  Completed.\n";
        } else {
            print "VM " . $this->params['hostname'] . " still exists.  Double Check status!\n";
        }
    }

    public function getVm()
    {
        $govc = new Govc($this->params['vmware_host'], $this->params['env']['vmware_username'], $this->params['env']['vmware_password'], $this->params['dc'], $this->params['hostname']);
        return $govc->getVmInfo();
    }
}