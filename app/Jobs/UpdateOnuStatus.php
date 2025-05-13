<?php

namespace App\Jobs;

use App\Models\OltDevice;
use App\Services\ZteCommandService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateOnuStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $olt;

    public function __construct(OltDevice $olt)
    {
        $this->olt = $olt;
    }

    public function handle(ZteCommandService $zteService)
    {
        Log::info("[JOB] UpdateOnuStatus started", [
            'olt_id' => $this->olt->id,
            'olt_name' => $this->olt->name,
            'ip_address' => $this->olt->ip_address
        ]);

        try {
            // Get ONU status from OLT
            $onuStates = $zteService->getOnuStates($this->olt);
            Log::info("[JOB] Retrieved ONU states", [
                'olt_id' => $this->olt->id,
                'states' => $onuStates
            ]);
            
            // Update ONU status in database
            foreach ($onuStates as $onuId => $state) {
                try {
                    Log::info("[JOB] Processing ONU", [
                        'olt_id' => $this->olt->id,
                        'onu_id' => $onuId,
                        'state' => $state
                    ]);

                    // Get ONU serial number
                    $serialNumber = $zteService->getOnuSerialNumber($this->olt, $state['interface']);

                    $onu = $this->olt->onus()->updateOrCreate(
                        ['onu_id' => $onuId],
                        [
                            'serial_number' => $serialNumber,
                            'is_online' => $state['is_online'],
                            'name' => $state['name'] ?? "ONU-{$onuId}",
                            'interface' => $state['interface'],
                            'admin_state' => $state['admin_state'],
                            'omcc_state' => $state['omcc_state'],
                            'phase_state' => $state['phase_state'],
                            'status' => $state['is_online'] ? 'online' : 'offline',
                            'last_seen' => now(),
                        ]
                    );

                    Log::info("[JOB] Updated ONU status", [
                        'olt_id' => $this->olt->id,
                        'onu_id' => $onuId,
                        'state' => $state,
                        'onu' => $onu->toArray()
                    ]);
                } catch (\Exception $e) {
                    Log::error("[JOB] Failed to update ONU status", [
                        'olt_id' => $this->olt->id,
                        'onu_id' => $onuId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continue with next ONU
                }
            }

            Log::info("[JOB] UpdateOnuStatus completed", [
                'olt_id' => $this->olt->id,
                'olt_name' => $this->olt->name,
                'total_onus' => count($onuStates)
            ]);
        } catch (\Exception $e) {
            Log::error("[JOB] UpdateOnuStatus failed", [
                'olt_id' => $this->olt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 