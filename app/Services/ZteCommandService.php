<?php

namespace App\Services;

use App\Models\OltDevice;
use App\Models\Onu;
use Exception;
use Illuminate\Support\Facades\Log;

class ZteCommandService
{
    protected $telnet;

    public function __construct(TelnetService $telnet)
    {
        $this->telnet = $telnet;
    }

    public function updateOnuStatus(OltDevice $olt)
    {
        try {
            Log::info("Starting ONU status update for OLT", [
                'olt_id' => $olt->id,
                'olt_name' => $olt->name,
                'ip_address' => $olt->ip_address
            ]);

            // Connect to OLT
            $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
            Log::info("Connected to OLT successfully");

            // Get all ONUs for this OLT
            $onus = $olt->onus;
            Log::info("Found ONUs to update", ['count' => $onus->count()]);

            foreach ($onus as $onu) {
                try {
                    Log::info("Updating status for ONU", [
                        'onu_id' => $onu->id,
                        'onu_name' => $onu->name,
                        'interface' => $onu->interface
                    ]);
                    
                    // Get ONU status
                    $status = $this->getOnuStatus($onu);
                    Log::info("ONU status retrieved", [
                        'onu_id' => $onu->id,
                        'status' => $status
                    ]);

                    // Update ONU in database
                    $onu->update([
                        'status' => $status['status'],
                        'rx_power' => $status['rx_power'],
                        'tx_power' => $status['tx_power'],
                        'temperature' => $status['temperature'],
                        'last_seen' => now(),
                    ]);

                    Log::info("ONU status updated successfully", ['onu_id' => $onu->id]);
                } catch (\Exception $e) {
                    Log::error("Failed to update ONU", [
                        'onu_id' => $onu->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continue with next ONU
                }
            }

            $this->telnet->disconnect();
            Log::info("ONU status update completed", ['olt_id' => $olt->id]);
            return true;
        } catch (\Exception $e) {
            Log::error("ONU status update failed", [
                'olt_id' => $olt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function getOnuStatus(Onu $onu)
    {
        try {
            Log::info("Getting ONU status", [
                'onu_id' => $onu->id,
                'onu_name' => $onu->name,
                'interface' => $onu->interface
            ]);

            // Get ONU status
            $output = $this->telnet->execute("show onu status {$onu->name}");
            Log::debug("Raw ONU status output", [
                'onu_id' => $onu->id,
                'output' => $output
            ]);

            // Parse status
            $status = [
                'status' => 'unknown',
                'rx_power' => null,
                'tx_power' => null,
                'temperature' => null
            ];

            // Parse status from output
            if (preg_match('/Status:\s*(\w+)/i', $output, $matches)) {
                $status['status'] = strtolower($matches[1]);
            }

            // Parse RX power
            if (preg_match('/RX Power:\s*([-\d.]+)\s*dBm/i', $output, $matches)) {
                $status['rx_power'] = (float) $matches[1];
            }

            // Parse TX power
            if (preg_match('/TX Power:\s*([-\d.]+)\s*dBm/i', $output, $matches)) {
                $status['tx_power'] = (float) $matches[1];
            }

            // Parse temperature
            if (preg_match('/Temperature:\s*([-\d.]+)\s*C/i', $output, $matches)) {
                $status['temperature'] = (float) $matches[1];
            }

            Log::info("Parsed ONU status", [
                'onu_id' => $onu->id,
                'status' => $status
            ]);
            return $status;
        } catch (\Exception $e) {
            Log::error("Failed to get ONU status", [
                'onu_id' => $onu->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function configureOnu(Onu $onu, array $config)
    {
        try {
            Log::info("Starting ONU configuration", [
                'onu_id' => $onu->id,
                'onu_name' => $onu->name,
                'config' => $config
            ]);

            // Connect to OLT
            $this->telnet->connect($onu->olt->ip_address, $onu->olt->port, $onu->olt->username, $onu->olt->password);
            Log::info("Connected to OLT successfully");

            // Build configuration commands
            $commands = $this->buildConfigurationCommands($onu, $config);
            Log::info("Built configuration commands", [
                'onu_id' => $onu->id,
                'commands' => $commands
            ]);

            // Execute each command
            foreach ($commands as $command) {
                $output = $this->telnet->execute($command);
                Log::info("Command executed", [
                    'onu_id' => $onu->id,
                    'command' => $command,
                    'output' => $output
                ]);
            }

            $this->telnet->disconnect();
            Log::info("ONU configuration completed successfully", ['onu_id' => $onu->id]);
            return true;
        } catch (\Exception $e) {
            Log::error("ONU configuration failed", [
                'onu_id' => $onu->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function buildConfigurationCommands(Onu $onu, array $config)
    {
        $commands = [];

        // Basic ONU configuration
        $commands[] = "configure terminal";
        $commands[] = "interface gpon {$onu->olt->gpon_interface}";
        $commands[] = "onu {$onu->name}";

        // Add configuration commands based on type
        if (isset($config['type'])) {
            switch ($config['type']) {
                case 'bridge':
                    $commands[] = "type bridge";
                    break;
                case 'router':
                    $commands[] = "type router";
                    break;
                case 'tr069':
                    $commands[] = "type tr069";
                    break;
            }
        }

        // Add VLAN configuration if provided
        if (isset($config['vlan'])) {
            $commands[] = "vlan {$config['vlan']}";
        }

        // Add bandwidth configuration if provided
        if (isset($config['bandwidth'])) {
            $commands[] = "bandwidth {$config['bandwidth']}";
        }

        // Exit configuration mode
        $commands[] = "exit";
        $commands[] = "exit";
        $commands[] = "exit";

        return $commands;
    }

    public function getOnuOpticalInfo(OltDevice $olt, Onu $onu)
    {
        try {
            Log::info("Getting ONU optical info", [
                'olt_id' => $olt->id,
                'onu_id' => $onu->id,
                'interface' => $onu->interface
            ]);

            $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
            $output = $this->telnet->execute("show gpon onu optical-info {$onu->interface}");
            $this->telnet->disconnect();

            $info = $this->parseOpticalInfo($output);
            Log::info("Retrieved ONU optical info", [
                'onu_id' => $onu->id,
                'info' => $info
            ]);

            return $info;
        } catch (\Exception $e) {
            Log::error("Failed to get ONU optical info", [
                'olt_id' => $olt->id,
                'onu_id' => $onu->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function parseOpticalInfo($output)
    {
        $rxPower = null;
        $txPower = null;

        if (preg_match('/Rx Power\s*:\s*([-\d.]+)\s*dBm/', $output, $matches)) {
            $rxPower = (float) $matches[1];
        }

        if (preg_match('/Tx Power\s*:\s*([-\d.]+)\s*dBm/', $output, $matches)) {
            $txPower = (float) $matches[1];
        }

        return [
            'rx_power' => $rxPower,
            'tx_power' => $txPower
        ];
    }

    public function getOnuStates(OltDevice $olt)
    {
        try {
            Log::info("[OLT] Starting ONU state retrieval", [
                'olt_id' => $olt->id,
                'olt_name' => $olt->name,
                'ip_address' => $olt->ip_address,
                'port' => $olt->port,
                'username' => $olt->username
            ]);
            
            // Connect to OLT using injected telnet service
            Log::info("[OLT] Attempting to connect to OLT", [
                'ip_address' => $olt->ip_address,
                'port' => $olt->port
            ]);
            
            try {
                $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
                Log::info("[OLT] Successfully connected to OLT");
            } catch (\Exception $e) {
                Log::error("[OLT] Failed to connect to OLT", [
                    'olt_id' => $olt->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception("Gagal terhubung ke OLT: " . $e->getMessage());
            }
            
            // Set terminal length to 0 to disable paging
            $this->telnet->execute("terminal length 0", false);
            
            // Get ONU states
            Log::info("[OLT] Executing command: show gpon onu state");
            try {
                $output = $this->telnet->execute("show gpon onu state");
                
                Log::debug("[OLT] Raw response from OLT after show gpon onu state", [
                    'output' => $output
                ]);
                
                if (empty($output)) {
                    throw new \Exception("Output kosong dari perintah show gpon onu state");
                }
                
                Log::info("[OLT] Command output received", [
                    'output_length' => strlen($output),
                    'first_100_chars' => substr($output, 0, 100),
                    'last_100_chars' => substr($output, -100)
                ]);
                
                // Check if output contains expected format
                if (strpos($output, 'OnuIndex') === false) {
                    Log::error("[OLT] Unexpected command output format", [
                        'output' => $output
                    ]);
                    throw new \Exception("Format output tidak sesuai. Pastikan perintah show gpon onu state berjalan dengan benar.");
                }
                
            } catch (\Exception $e) {
                Log::error("[OLT] Failed to execute command", [
                    'olt_id' => $olt->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception("Gagal menjalankan perintah: " . $e->getMessage());
            }
            
            // Parse ONU states
            try {
                $onuStates = $this->parseOnuStates($output);
                
                if (empty($onuStates)) {
                    Log::warning("[OLT] No ONU states found in output", [
                        'output' => $output
                    ]);
                    throw new \Exception("Tidak ada data ONU yang ditemukan dalam output");
                }
                
                Log::info("[OLT] Parsed ONU states", [
                    'olt_id' => $olt->id,
                    'total_onus' => count($onuStates),
                    'states' => $onuStates
                ]);
            } catch (\Exception $e) {
                Log::error("[OLT] Failed to parse ONU states", [
                    'olt_id' => $olt->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'raw_output' => $output
                ]);
                throw new \Exception("Gagal memparse status ONU: " . $e->getMessage());
            }
            
            $this->telnet->disconnect();
            Log::info("[OLT] Disconnected from OLT");
            
            return $onuStates;
        } catch (\Exception $e) {
            Log::error("[OLT] Failed to get ONU states", [
                'olt_id' => $olt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Pastikan koneksi ditutup jika masih terbuka
            try {
                $this->telnet->disconnect();
            } catch (\Exception $disconnectError) {
                Log::error("[OLT] Failed to disconnect after error", [
                    'olt_id' => $olt->id,
                    'error' => $disconnectError->getMessage()
                ]);
            }
            throw $e;
        }
    }

    protected function parseOnuStates($output)
    {
        $states = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            // Skip header, separator, dan baris kosong
            if (preg_match('/^OnuIndex|^-+|^\s*$/', $line)) {
                continue;
            }
            // Contoh baris: 1/2/1:1     enable       enable      working      1(GPON)
            if (preg_match('/^(\d+\/\d+\/\d+:\d+)\s+(\w+)\s+(\w+)\s+(\w+)\s+\d+\(GPON\)/', $line, $matches)) {
                $states[] = [
                    'interface' => $matches[1],
                    'admin_state' => strtolower($matches[2]),
                    'omcc_state' => strtolower($matches[3]),
                    'phase_state' => strtolower($matches[4]),
                    'is_online' => strtolower($matches[4]) === 'working',
                ];
            }
        }
        return $states;
    }

    public function getOnuSerialNumber(OltDevice $olt, $interface)
    {
        try {
            Log::info("Getting ONU serial number", [
                'olt_id' => $olt->id,
                'interface' => $interface
            ]);

            // Get ONU states which includes serial numbers
            $onuStates = $this->getOnuStates($olt);
            
            // Find the matching ONU state
            foreach ($onuStates as $state) {
                if ($state['interface'] === $interface) {
                    return $state['serial_number'] ?? null;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to get ONU serial number", [
                'olt_id' => $olt->id,
                'interface' => $interface,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getOnuConfig(OltDevice $olt, $interface)
    {
        try {
            Log::info("Getting ONU config", [
                'olt_id' => $olt->id,
                'interface' => $interface
            ]);

            $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
            $output = $this->telnet->execute("show running-config interface gpon-onu_{$interface}");
            $this->telnet->disconnect();

            $config = $this->parseOnuConfig($output);
            Log::info("Parsed ONU config", [
                'interface' => $interface,
                'config' => $config
            ]);

            return $config;
        } catch (\Exception $e) {
            Log::error("Failed to get ONU config", [
                'olt_id' => $olt->id,
                'interface' => $interface,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function parseOnuConfig($output)
    {
        $description = null;
        $tconts = [];
        $gemports = [];
        $servicePorts = [];

        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            if (stripos($line, 'description ') === 0) {
                $description = trim(substr($line, strlen('description')));
            } elseif (stripos($line, 'tcont ') === 0) {
                $tconts[] = $line;
            } elseif (stripos($line, 'gemport ') === 0) {
                $gemports[] = $line;
            } elseif (stripos($line, 'service-port ') === 0) {
                $servicePorts[] = $line;
            }
        }

        return [
            'description' => $description,
            'tconts' => $tconts,
            'gemports' => $gemports,
            'service_ports' => $servicePorts,
        ];
    }

    public function getOnuOpticalPower(OltDevice $olt, $interface)
    {
        try {
            Log::info("Getting ONU optical power", [
                'olt_id' => $olt->id,
                'interface' => $interface
            ]);

            $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
            $output = $this->telnet->execute("show pon power attenuation gpon-onu_{$interface}");
            $this->telnet->disconnect();

            $rxOnu = $this->parseOnuOpticalPower($output);
            Log::info("Parsed ONU optical power", [
                'interface' => $interface,
                'rx_onu' => $rxOnu
            ]);

            return $rxOnu;
        } catch (\Exception $e) {
            Log::error("Failed to get ONU optical power", [
                'olt_id' => $olt->id,
                'interface' => $interface,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function parseOnuOpticalPower($output)
    {
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            // Cari baris yang diawali dengan 'down'
            if (stripos($line, 'down') === 0) {
                // down    Tx :6.515(dbm)        Rx:-20.172(dbm)      26.687(dB)
                if (preg_match('/Rx:([\-\d\.]+)\(dbm\)/i', $line, $matches)) {
                    return (float)$matches[1];
                }
            }
        }
        return null;
    }

    public function updateRxPowerByInterface(OltDevice $olt)
    {
        $onus = $olt->onus;
        $interfaces = $onus->pluck('interface')->map(function($iface) {
            // Ambil hanya bagian 1/2/1 dari 1/2/1:1
            return preg_replace('/:(\d+)$/', '', $iface);
        })->unique();

        $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
        $this->telnet->execute("terminal length 0", false);
        
        foreach ($interfaces as $interface) {
            $command = "show pon power onu-rx gpon-olt_{$interface}";
            $output = $this->telnet->execute($command);
            // Parsing output, contoh baris: gpon-onu_1/2/1:1    -20.172(dbm) atau N/A
            $rxPowers = [];
            foreach (explode("\n", $output) as $line) {
                if (preg_match('/gpon-onu_(\d+\/\d+\/\d+:\d+)\s+([\-\d\.]+)\(dbm\)/i', trim($line), $matches)) {
                    $rxPowers[$matches[1]] = (float)$matches[2];
                } elseif (preg_match('/gpon-onu_(\d+\/\d+\/\d+:\d+)\s+N\/A/i', trim($line), $matches)) {
                    $rxPowers[$matches[1]] = null;
                }
            }
            // Update semua ONU di interface ini
            foreach ($onus as $onu) {
                if (isset($rxPowers[$onu->interface])) {
                    $onu->rx_power = $rxPowers[$onu->interface];
                    $onu->save();
                }
            }
        }

        $this->telnet->disconnect();
    }

    public function updateSerialNumberByInterface(OltDevice $olt)
    {
        $onus = $olt->onus;
        $interfaces = $onus->pluck('interface')->map(function($iface) {
            // Ambil hanya bagian 1/2/1 dari 1/2/1:1
            return preg_replace('/:(\d+)$/', '', $iface);
        })->unique();

        $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
        $this->telnet->execute("terminal length 0", false);

        foreach ($interfaces as $interface) {
            $command = "show gpon onu baseinfo gpon-olt_{$interface}";
            $output = $this->telnet->execute($command);
            // Parsing output, contoh baris: gpon-onu_1/2/1:1    ZTE-F660    sn      SN:ZTEGC9564383         ready
            $serials = [];
            foreach (explode("\n", $output) as $line) {
                if (preg_match('/gpon-onu_(\d+\/\d+\/\d+:\d+)\s+\S+\s+\S+\s+SN:([A-Z0-9]+)\s+/', trim($line), $matches)) {
                    $serials[$matches[1]] = $matches[2];
                }
            }
            // Update semua ONU di interface ini
            foreach ($onus as $onu) {
                if (isset($serials[$onu->interface])) {
                    $onu->serial_number = $serials[$onu->interface];
                    $onu->save();
                }
            }
        }

        $this->telnet->disconnect();
    }

    public function getOnuDetailInfo(OltDevice $olt, $interface)
    {
        $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
        $this->telnet->execute("terminal length 0", false);
        $output = $this->telnet->execute("show gpon onu detail-info gpon-onu_{$interface}");
        $this->telnet->disconnect();

        $serialNumber = null;
        $distance = null;
        $onlineDuration = null;

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/Serial number:\s*([A-Z0-9]+)/i', $line, $m)) {
                $serialNumber = $m[1];
            }
            if (preg_match('/ONU Distance:\s*([\d.]+m)/i', $line, $m)) {
                $distance = $m[1];
            }
            if (preg_match('/Online Duration:\s*([\dhms :]+)/i', $line, $m)) {
                $onlineDuration = trim($m[1]);
            }
        }
        return [
            'serial_number' => $serialNumber,
            'distance' => $distance,
            'online_duration' => $onlineDuration,
        ];
    }

    public function updateDescriptionByInterface(OltDevice $olt)
    {
        $onus = $olt->onus;
        $interfaces = $onus->pluck('interface')->unique();

        $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);

        foreach ($interfaces as $interface) {
            $output = $this->telnet->execute("show running-config interface gpon-onu_{$interface}");
            $description = null;
            foreach (explode("\n", $output) as $line) {
                if (preg_match('/^\s*description\s+(.+)/i', trim($line), $matches)) {
                    $description = trim($matches[1]);
                    break;
                }
            }
            // Update semua ONU di interface ini
            foreach ($onus as $onu) {
                if ($onu->interface === $interface) {
                    $onu->description = $description;
                    $onu->save();
                }
            }
        }

        $this->telnet->disconnect();
    }

    public function getOnuWanInfo(OltDevice $olt, $interface)
    {
        $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
        $output = $this->telnet->execute("show gpon remote-onu wan-ip gpon-onu_{$interface}");
        $this->telnet->disconnect();

        $result = [
            'ip' => null,
            'mask' => null,
            'gateway' => null,
            'dns1' => null,
            'dns2' => null,
            'username' => null,
            'password' => null,
            'mac' => null,
        ];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/Current IP:\s*([\d.]+)/i', $line, $m)) $result['ip'] = $m[1];
            if (preg_match('/Current mask:\s*([\d.]+)/i', $line, $m)) $result['mask'] = $m[1];
            if (preg_match('/Current gateway:\s*([\d.]+)/i', $line, $m)) $result['gateway'] = $m[1];
            if (preg_match('/Current primary DNS:\s*([\d.]+)/i', $line, $m)) $result['dns1'] = $m[1];
            if (preg_match('/Current secondary DNS:\s*([\d.]+)/i', $line, $m)) $result['dns2'] = $m[1];
            if (preg_match('/Username:\s*(\S+)/i', $line, $m)) $result['username'] = $m[1];
            if (preg_match('/Password:\s*(\S+)/i', $line, $m)) $result['password'] = $m[1];
            if (preg_match('/MAC address:\s*([\w.]+)/i', $line, $m)) $result['mac'] = $m[1];
        }
        return $result;
    }

    /**
     * Ambil info equipment ONU (khusus Model) dari perintah show gpon remote-onu equip gpon-onu_<interface>
     */
    public function getOnuEquipInfo(OltDevice $olt, $interface)
    {
        $this->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
        $output = $this->telnet->execute("show gpon remote-onu equip gpon-onu_{$interface}");
        $this->telnet->disconnect();

        $model = null;
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/Model:\s*(.+)/i', $line, $m)) {
                $model = trim($m[1]);
                break;
            }
        }
        return [
            'model' => $model
        ];
    }
} 