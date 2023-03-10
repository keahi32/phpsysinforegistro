<?php
/**
 * FreeBSD System Class
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PSI FreeBSD OS class
 * @author    Michael Cramer <BigMichi1@users.sourceforge.net>
 * @copyright 2009 phpSysInfo
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License version 2, or (at your option) any later version
 * @version   SVN: $Id: class.FreeBSD.inc.php 696 2012-09-09 11:24:04Z namiltd $
 * @link      http://phpsysinfo.sourceforge.net
 */
 /**
 * FreeBSD sysinfo class
 * get all the required information from FreeBSD system
 *
 * @category  PHP
 * @package   PSI FreeBSD OS class
 * @author    Michael Cramer <BigMichi1@users.sourceforge.net>
 * @copyright 2009 phpSysInfo
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License version 2, or (at your option) any later version
 * @version   Release: 3.0
 * @link      http://phpsysinfo.sourceforge.net
 */
class FreeBSD extends BSDCommon
{
    /**
     * define the regexp for log parser
     */
    public function __construct($blockname = false)
    {
        parent::__construct($blockname);
        $this->setCPURegExp1("/CPU: (.*) \((.*)-MHz (.*)\)/");
        $this->setCPURegExp2("/(.*) ([0-9]+) ([0-9]+) ([0-9]+) ([0-9]+)/");
        $this->setSCSIRegExp1("/^(.*): <(.*)> .*SCSI.*device/");
        $this->setSCSIRegExp2("/^(da[0-9]+): (.*)MB /");
        $this->setSCSIRegExp3("/^(da[0-9]+|cd[0-9]+): Serial Number (.*)/");
        $this->setPCIRegExp1("/(.*): <(.*)>(.*) pci[0-9]+$/");
        $this->setPCIRegExp2("/(.*): <(.*)>.* at [.0-9]+ irq/");
    }

    /**
     * get network information
     *
     * @return void
     */
    private function _network()
    {
        $dev = null;
        if (CommonFunctions::executeProgram('netstat', '-nibd', $netstat, PSI_DEBUG)) {
            $lines = preg_split("/\n/", $netstat, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($lines as $line) {
                $ar_buf = preg_split("/\s+/", $line);
                if (!empty($ar_buf[0])) {
                    if (preg_match('/^<Link/i', $ar_buf[2])) {
                        $dev = new NetDevice();
                        $dev->setName($ar_buf[0]);
                        if ((strlen($ar_buf[3]) < 17) && ($ar_buf[0] != $ar_buf[3])) { /* no MAC or dev name*/
                            if (isset($ar_buf[11]) && (trim($ar_buf[11]) != '')) { /* Idrop column exist*/
                              $dev->setTxBytes($ar_buf[9]);
                              $dev->setRxBytes($ar_buf[6]);
                              $dev->setErrors($ar_buf[4] + $ar_buf[8]);
                              $dev->setDrops($ar_buf[11] + $ar_buf[5]);
                            } else {
                              $dev->setTxBytes($ar_buf[8]);
                              $dev->setRxBytes($ar_buf[5]);
                              $dev->setErrors($ar_buf[4] + $ar_buf[7]);
                              $dev->setDrops($ar_buf[10]);
                            }
                        } else {
                            if (isset($ar_buf[12]) && (trim($ar_buf[12]) != '')) { /* Idrop column exist*/
                              $dev->setTxBytes($ar_buf[10]);
                              $dev->setRxBytes($ar_buf[7]);
                              $dev->setErrors($ar_buf[5] + $ar_buf[9]);
                              $dev->setDrops($ar_buf[12] + $ar_buf[6]);
                            } else {
                              $dev->setTxBytes($ar_buf[9]);
                              $dev->setRxBytes($ar_buf[6]);
                              $dev->setErrors($ar_buf[5] + $ar_buf[8]);
                              $dev->setDrops($ar_buf[11]);
                            }
                        }
                        if (defined('PSI_SHOW_NETWORK_INFOS') && (PSI_SHOW_NETWORK_INFOS) && (CommonFunctions::executeProgram('ifconfig', $ar_buf[0].' 2>/dev/null', $bufr2, PSI_DEBUG))) {
                            $bufe2 = preg_split("/\n/", $bufr2, -1, PREG_SPLIT_NO_EMPTY);
                            foreach ($bufe2 as $buf2) {
                                if (preg_match('/^\s+ether\s+(\S+)/i', $buf2, $ar_buf2)) {
                                    if (!defined('PSI_HIDE_NETWORK_MACADDR') || !PSI_HIDE_NETWORK_MACADDR) $dev->setInfo(($dev->getInfo()?$dev->getInfo().';':'').preg_replace('/:/', '-', strtoupper($ar_buf2[1])));
                                } elseif (preg_match('/^\s+inet\s+(\S+)\s+netmask/i', $buf2, $ar_buf2)) {
                                    $dev->setInfo(($dev->getInfo()?$dev->getInfo().';':'').$ar_buf2[1]);
                                } elseif ((preg_match('/^\s+inet6\s+([^\s%]+)\s+prefixlen/i', $buf2, $ar_buf2)
                                      || preg_match('/^\s+inet6\s+([^\s%]+)%\S+\s+prefixlen/i', $buf2, $ar_buf2))
                                      && ($ar_buf2[1]!="::") && !preg_match('/^fe80::/i', $ar_buf2[1])) {
                                    $dev->setInfo(($dev->getInfo()?$dev->getInfo().';':'').strtolower($ar_buf2[1]));
                                } elseif (preg_match('/^\s+media:\s+/i', $buf2) && preg_match('/[\(\s](\d+)(G*)base/i', $buf2, $ar_buf2)) {
                                    if (isset($ar_buf2[2]) && strtoupper($ar_buf2[2])=="G") {
                                        $unit = "G";
                                    } else {
                                        $unit = "M";
                                    }
                                    if (preg_match('/[<\s]([^\s<]+)-duplex/i', $buf2, $ar_buf3))
                                        $dev->setInfo(($dev->getInfo()?$dev->getInfo().';':'').$ar_buf2[1].$unit.'b/s '.strtolower($ar_buf3[1]));
                                    else
                                        $dev->setInfo(($dev->getInfo()?$dev->getInfo().';':'').$ar_buf2[1].$unit.'b/s');
                                }
                            }
                        }
                        $this->sys->setNetDevices($dev);
                    }
                }
            }
        }
    }

    /**
     * get icon name and distro extended check
     *
     * @return void
     */
    private function _distroicon()
    {
        if (CommonFunctions::rfts('/etc/version', $version, 1, 4096, false) && (($version=trim($version)) != '')) {
            if (extension_loaded('pfSense')) { // pfSense detection
                $this->sys->setDistribution('pfSense '. $version);
                $this->sys->setDistributionIcon('pfSense.png');
            } elseif (preg_match('/^FreeNAS/i', $version)) { // FreeNAS detection
                $this->sys->setDistribution($version);
                $this->sys->setDistributionIcon('FreeNAS.png');
            } elseif (preg_match('/^TrueNAS/i', $version)) { // TrueNAS detection
                $this->sys->setDistribution($version);
                $this->sys->setDistributionIcon('TrueNAS.png');
            } else {
                $this->sys->setDistributionIcon('FreeBSD.png');
            }
        } else {
            $this->sys->setDistributionIcon('FreeBSD.png');
        }
    }

    /**
     * extend the memory information with additional values
     *
     * @return void
     */
    private function _memoryadditional()
    {
        $pagesize = $this->grabkey("hw.pagesize");
        $this->sys->setMemCache($this->grabkey("vm.stats.vm.v_cache_count") * $pagesize);
        $this->sys->setMemApplication(($this->grabkey("vm.stats.vm.v_active_count") + $this->grabkey("vm.stats.vm.v_wire_count")) * $pagesize);
        $this->sys->setMemBuffer($this->sys->getMemUsed() - $this->sys->getMemApplication() - $this->sys->getMemCache());
    }

    /**
     * Processes
     *
     * @return void
     */
    protected function _processes()
    {
        if (CommonFunctions::executeProgram('ps', 'aux', $bufr, PSI_DEBUG)) {
            $lines = preg_split("/\n/", $bufr, -1, PREG_SPLIT_NO_EMPTY);
            $processes['*'] = 0;
            foreach ($lines as $line) {
                if (preg_match("/^\S+\s+\d+\s+\S+\s+\S+\s+\d+\s+\d+\s+\S+\s+(\w)/", $line, $ar_buf)) {
                    $processes['*']++;
                    $state = $ar_buf[1];
                    if ($state == 'L') $state = 'D'; //linux format
                    elseif ($state == 'I') $state = 'S';
                    if (isset($processes[$state])) {
                        $processes[$state]++;
                    } else {
                        $processes[$state] = 1;
                    }
                }
            }
            if ($processes['*'] > 0) {
                $this->sys->setProcesses($processes);
            }
        }
    }

    /**
     * get the information
     *
     * @see BSDCommon::build()
     *
     * @return void
     */
    public function build()
    {
        parent::build();
        if (!$this->blockname || $this->blockname==='vitals') {
            $this->_distroicon();
            $this->_processes();
        }
        if (!$this->blockname || $this->blockname==='memory') {
            $this->_memoryadditional();
        }
        if (!$this->blockname || $this->blockname==='network') {
            $this->_network();
        }
    }
}
