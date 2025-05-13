<?php

namespace App\Jobs;

use App\Models\OltDevice;
use App\Models\Onu;
use App\Services\ZteCommandService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateOnuDetailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $oltId;

    public function __construct($oltId)
    {
        $this->oltId = $oltId;
    }

    public function handle(ZteCommandService $zteService)
    {
        $olt = OltDevice::find($this->oltId);
        if (!$olt) return;
        $onus = Onu::where('olt_id', $olt->id)->get();
        if ($onus->isEmpty()) return;

        try {
            // Buka satu koneksi Telnet
            $zteService->telnet->connect($olt->ip_address, $olt->port, $olt->username, $olt->password);
            foreach ($onus as $onu) {
                try {
                    // Update Rx power saja
                    $rxPower = $zteService->getOnuOpticalPower($olt, $onu->interface);
                    $onu->update([
                        'rx_power' => $rxPower,
                    ]);
                    Log::info('[JOB] ONU rx power updated', [
                        'onu_id' => $onu->id,
                        'interface' => $onu->interface
                    ]);
                } catch (\Exception $e) {
                    Log::error('[JOB] Failed to update ONU rx power', [
                        'onu_id' => $onu->id,
                        'interface' => $onu->interface,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            $zteService->telnet->disconnect();
        } catch (\Exception $e) {
            Log::error('[JOB] Failed to update ONU rx power (batch)', [
                'olt_id' => $olt->id,
                'error' => $e->getMessage()
            ]);
        }
    }
} 