<?php

namespace App\Console\Commands;

use App\Models\OltDevice;
use App\Services\ZteCommandService;
use Illuminate\Console\Command;

class UpdateOnuStatus extends Command
{
    protected $signature = 'onu:update-status';
    protected $description = 'Update ONU status for all active OLT devices';

    protected $zteCommand;

    public function __construct(ZteCommandService $zteCommand)
    {
        parent::__construct();
        $this->zteCommand = $zteCommand;
    }

    public function handle()
    {
        $olts = OltDevice::where('is_active', true)->get();

        foreach ($olts as $olt) {
            try {
                $this->info("Updating ONU status for OLT: {$olt->name}");
                
                $statuses = $this->zteCommand->getOnuStatus($olt);
                
                foreach ($statuses as $status) {
                    $onu = $olt->onus()->where('interface', $status['interface'])->first();
                    if ($onu) {
                        $opticalInfo = $this->zteCommand->getOnuOpticalInfo($olt, $onu);
                        $onu->update([
                            'status' => $status['status'],
                            'rx_power' => $opticalInfo['rx_power'],
                            'tx_power' => $opticalInfo['tx_power']
                        ]);
                    }
                }

                $this->info("Successfully updated ONU status for OLT: {$olt->name}");
            } catch (\Exception $e) {
                $this->error("Failed to update ONU status for OLT {$olt->name}: {$e->getMessage()}");
            }
        }
    }
} 