<?php

namespace App\Http\Controllers;

use App\Models\Onu;
use App\Services\ZteCommandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnuController extends Controller
{
    protected $zteService;

    public function __construct(ZteCommandService $zteService)
    {
        $this->zteService = $zteService;
    }

    public function configure(Request $request, Onu $onu)
    {
        try {
            $config = $request->validate([
                'description' => 'required|string|max:255',
            ]);

            $onu->description = $config['description'];
            $onu->save();

            return response()->json([
                'success' => true,
                'message' => 'Description updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('[ONU] Failed to update description', [
                'onu_id' => $onu->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update description: ' . $e->getMessage()
            ], 500);
        }
    }

    public function detail(Onu $onu)
    {
        try {
            $olt = $onu->oltDevice;
            $interface = $onu->interface;
            $zteService = app(\App\Services\ZteCommandService::class);
            $config = $zteService->getOnuConfig($olt, $interface);
            $detailInfo = $zteService->getOnuDetailInfo($olt, $interface);
            $wanInfo = $zteService->getOnuWanInfo($olt, $interface);
            $equipInfo = $zteService->getOnuEquipInfo($olt, $interface);
            // if ($equipInfo && isset($equipInfo['model'])) {
            //     $detailInfo['model'] = $equipInfo['model'];
            // }

            // Add debug logging
            Log::info('ONU Detail Data', [
                'onu_id' => $onu->id,
                'interface' => $interface,
                'config' => $config,
                'detailInfo' => $detailInfo,
                'wanInfo' => $wanInfo,
                'equipInfo' => $equipInfo
            ]);

            if (request()->expectsJson()) {
                return response()->json([
                    'onu' => $onu,
                    'config' => $config,
                    'detailInfo' => $detailInfo,
                    'wanInfo' => $wanInfo,
                    'equipInfo' => $equipInfo
                ]);
            }

            // Jika bukan AJAX, redirect ke halaman OLT (atau bisa tampilkan error)
            return redirect()->route('olt.show', $olt->id);
        } catch (\Exception $e) {
            if (request()->expectsJson()) {
                return response()->json([
                    'error' => 'Gagal mengambil detail ONU: ' . $e->getMessage()
                ], 500);
            }
            return back()->with('error', 'Gagal mengambil detail ONU: ' . $e->getMessage());
        }
    }

    public function updateDescription(Onu $onu)
    {
        try {
            $olt = $onu->oltDevice;
            $interface = $onu->interface;
            $zteService = app(\App\Services\ZteCommandService::class);
            $output = $zteService->getOnuConfig($olt, $interface);
            $description = $output['description'] ?? null;
            $onu->description = $description;
            $onu->save();
            return response()->json([
                'description' => $description
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal update description: ' . $e->getMessage()
            ], 500);
        }
    }
} 