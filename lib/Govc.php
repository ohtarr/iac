<?php

/**
 * lib/govc.class.php.
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

class Govc
{
    public $hostname;
    public $host;
    public $username;
    public $password;
    public $dc;

    public function __construct($host, $username, $password, $dc, $hostname)
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->dc = $dc;
        $this->hostname = $hostname;
        $this->setEnv();
    }

    public function setEnv()
    {
        //vmware url
        putenv("GOVC_URL={$this->host}");
        //vmware disable certificate security
        putenv("GOVC_INSECURE=1");
        //vmware username
        putenv("GOVC_USERNAME={$this->username}");
        //vmware password
        putenv("GOVC_PASSWORD={$this->password}");
    }

    public function getVmInfo()
    {
        if(!$this->hostname)
        {
            print "No hostname found!\n";
            return null;
        }
        $json = shell_exec("govc vm.info -dc=\"{$this->dc}\" -json {$this->hostname}");
        $object = json_decode($json);
        return $object;
    }

    public function getVmDeviceInfo()
    {
        $json = shell_exec("govc device.info -dc=\"{$this->dc}\" -json -vm {$this->hostname}");
        $object = json_decode($json);
        return $object->Devices;
    }
    
    public function vmExists()
    {
        $vminfo = $this->getVmInfo($this->hostname);
        if($vminfo->VirtualMachines)
        {
            return true;
        }
    }
    
    public function getVmPower()
    {
        $vminfo = $this->getVmInfo($this->hostname);
        $powerstate = $vminfo->VirtualMachines[0]->Runtime->PowerState;
        if($powerstate == "poweredOn")
        {
            return true;
        }
    }
    
    public function getDisks()
    {
        $devices = $this->getVmDeviceInfo();
        foreach($devices as $device)
        {
            if($device->Type == "VirtualDisk")
            {
                $disks[] = $device;
            }
        }
        return $disks;
    }

    public function getFloppies()
    {
        $floppies = [];
        $devices = $this->getVmDeviceInfo();
        foreach($devices as $device)
        {
            if($device->Type == "VirtualFloppy")
            {
                $floppies[] = $device;
            }
        }
        return $floppies;
    }

    public function deleteAllFloppies()
    {
        $floppies = $this->getFloppies();
        foreach($floppies as $floppy)
        {
            $this->removeDevice($floppy->Name);
        }
    }

    public function findDisk($diskpath)
    {
        $devices = $this->getVmDeviceInfo();
        foreach($devices as $device)
        {
            if(isset($device->Backing->FileName))
            {
                $filename = $device->Backing->FileName;        
                //$diskreg = "/" . $disk . "/";
                $diskreg = "/" . str_replace("/","\/",$diskpath) . "/";
                if(preg_match($diskreg,$device->Backing->FileName, $hits))
                {
                    return $device;
                }
            }
        }
    }

    public function attachDisk($datastore, $diskpath)
    {
        print "Running attachDisk for datastore " . $datastore . " and disk {$diskpath}.\n";
        if($this->vmExists())
        {
            print "Vm {$this->hostname} exists, proceeding...\n";
            $device = $this->findDisk($diskpath);
            if(!$device)
            {
                print "Disk {$diskpath} is not already attached...proceeding with attachment.\n";
                shell_exec("govc vm.disk.attach -dc=\"{$this->dc}\" -vm {$this->hostname} -disk=\"{$diskpath}\" -ds=\"{$datastore}\" -link=false");
                if($device = $this->findDisk($diskpath))
                {
                    print "Disk {$diskpath} is now attached to vm {$this->hostname}.\n";
                    return $device;
                }
            } else {
                print "Disk {$diskpath} is already attached to vm {$this->hostname}.  Cancelling attachment.\n"; 
                return $device;
            }
        }
    }

    public function detachDisk($diskpath)
    {
        $device = $this->findDisk($diskpath);

        if($device)
        {
            print "Disk {$diskpath} is currently attached to vm {$this->hostname}.  Proceeding with detachment.\n";
            shell_exec("govc device.remove -dc=\"{$this->dc}\" -vm {$this->hostname} -keep  {$device->Name}");
            if(!$this->findDisk($diskpath))
            {
                print "Disk {$diskpath} is no longer attached to vm {$this->hostname}.\n";
                return true;
            } else {
                print "Failed to detach disk {$diskpath} from vm {$this->hostname}!\n";
                return false;
            }
        } else {
            print "Disk {$diskpath} is not currently attached to vm {$this->hostname}.\n";
            return true;
        }
    }

    public function destroyVm()
    {
        if($this->vmExists())
        {
            print "vm {$this->hostname} exists... destroying vm!\n";
            shell_exec("govc vm.destroy -dc=\"{$this->dc}\" {$this->hostname}");
            if(!$this->vmExists())
            {
                return true;
            } else {
                return false;
            }
        } else {
            print "vm {$this->hostname} doesn't exist!\n";
        }
    }

    public function modifyNotes($notes)
    {
        if($this->vmExists())
        {
            shell_exec("govc vm.change -dc=\"{$this->dc}\" -vm {$this->hostname} -annotation=\"{$notes}\"");
        }
    }

    public function modifyDiskSize($gigabytes)
    {
        if($this->vmExists())
        {
            shell_exec("govc vm.disk.change -dc=\"{$this->dc}\" -vm {$this->hostname} -disk.name disk-1000-0 -size {$gigabytes}G");
        }
        
    }

    public function modifyResources($cores,$ramgb)
    {
        $rammb = $ramgb * 1024;
        if($this->vmExists())
        {
            shell_exec("govc vm.change -dc=\"{$this->dc}\" -vm {$this->hostname} -c={$cores} -m={$rammb}");
        }
    }

    public function removeDevice($devicename)
    {
        if($this->vmExists())
        {
            shell_exec("govc device.remove -dc=\"{$this->dc}\" -vm={$this->hostname} {$devicename}");
        }
    }

    //govc vm.network.change -dc="{$this->dc}" -vm netobswld001 -net="Network-Automation-10.123.123.0%2f24-VLAN333" -net.address 00:50:56:a9:3d:9a ethernet-0

    public function powerOn()
    {
        if($this->vmExists())
        {
            print "Powering on vm {$this->hostname}.\n";
            shell_exec("govc vm.power -on=true -dc=\"{$this->dc}\" {$this->hostname}");
        }
    }

    public function powerOff()
    {
        if($this->vmExists())
        {
            print "Powering off vm {$this->hostname}.\n";
            shell_exec("govc vm.power -off=true -dc=\"{$this->dc}\" {$this->hostname}");
        }
    }

    public function powerReset()
    {
        if($this->vmExists())
        {
            print "Resetting power on vm {$this->hostname}.\n";
            shell_exec("govc vm.power -reset=true -dc=\"{$this->dc}\" {$this->hostname}");
        }
    }

    public function shutdownGuest()
    {
        if($this->vmExists())
        {
            print "Shutting down guest OS on vm {$this->hostname}.\n";
            shell_exec("govc vm.power -s=true -dc=\"{$this->dc}\" {$this->hostname}");
        }
    }

    public function rebootGuest()
    {
        if($this->vmExists())
        {
            print "Rebooting OS on vm {$this->hostname}.\n";
            shell_exec("govc vm.power -r=true -dc=\"{$this->dc}\" {$this->hostname}");
        }
    }

    public function networkChangeMac($network,$mac)
    {
        shell_exec("govc vm.network.change -dc=\"{$this->dc}\" -vm {$this->hostname} -net=\"{$network}\" -net.address {$mac} ethernet-0");
    }

    public function createFolder($datastore,$foldername)
    {
        shell_exec("govc datastore.mkdir -dc=\"{$this->dc}\" -ds=\"{$datastore}\" {$foldername}");
    }

    public function deleteFolder($datastore,$foldername)
    {
        shell_exec("govc datastore.rm -dc=\"{$this->dc}\" -ds=\"{$datastore}\" {$foldername}");
    }

    public function createDisk($datastore,$diskname,$sizegb)
    {
        shell_exec("govc datastore.disk.create -dc=\"{$this->dc}\" -ds=\"{$datastore}\" -size {$sizegb}GB {$diskname}");
    }

    public function deleteDisk($datastore,$diskname)
    {
        shell_exec("govc datastore.rm -dc=\"{$this->dc}\" -ds=\"{$datastore}\" {$diskname}");
    }

    public function deployVm($vmspecfile,$datastore,$pool,$ova_file)
    {
        $cmd = "govc import.ova -options=\"{$vmspecfile}\" -dc=\"{$this->dc}\" -ds=\"{$datastore}\" -pool=\"{$pool}\" {$ova_file}";
        shell_exec($cmd);
    }

}