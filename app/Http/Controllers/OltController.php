<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateOnuStatus;
use App\Models\OltDevice;
use App\Models\Onu;
use App\Services\ZteCommandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OltController extends Controller
{
    protected $zteService;

    public function __construct(ZteCommandService $zteService)
    {
        $this->zteService = $zteService;
    }

    public function index()
    {
        $olts = OltDevice::all();
        return view('olt.index', compact('olts'));
    }

    public function show(OltDevice $olt)
    {
        $olt->load('onus');
        return view('olt.show', compact('olt'));
    }

    public function updateStatus(OltDevice $olt)
    {
        try {
            Log::info("[OLT] Starting ONU status update", [
                'olt_id' => $olt->id,
                'olt_name' => $olt->name
            ]);

            // Get ONU states
            $onuStates = $this->zteService->getOnuStates($olt);
            
            if (empty($onuStates)) {
                Log::warning("[OLT] No ONU states found", [
                    'olt_id' => $olt->id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada ONU yang ditemukan pada OLT ini. Pastikan OLT terhubung dengan benar dan memiliki ONU yang terdaftar.'
                ], 404);
            }
            
            // Process each ONU state
            $processedCount = 0;
            $errorCount = 0;
            $errors = [];
            
            foreach ($onuStates as $onuId => $onuState) {
                try {
                    Log::info("[OLT] Processing ONU", [
                        'olt_id' => $olt->id,
                        'onu_id' => $onuId,
                        'onu_interface' => $onuState['interface']
                    ]);

                    // Update or create ONU record (tanpa serial number)
                    $onu = Onu::updateOrCreate(
                        [
                            'olt_device_id' => $olt->id,
                            'interface' => $onuState['interface']
                        ],
                        [
                            'olt_device_id' => $olt->id,
                            'is_online' => $onuState['is_online'],
                            'admin_state' => $onuState['admin_state'],
                            'omcc_state' => $onuState['omcc_state'],
                            'phase_state' => $onuState['phase_state'],
                            'status' => $onuState['is_online'] ? 'online' : 'offline',
                            'last_seen' => now()
                        ]
                    );

                    $processedCount++;
                    Log::info("[OLT] ONU processed successfully", [
                        'olt_id' => $olt->id,
                        'onu_id' => $onu->id,
                        'onu_interface' => $onu->interface
                    ]);
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'onu_id' => $onuId,
                        'interface' => $onuState['interface'],
                        'error' => $e->getMessage()
                    ];
                    Log::error("[OLT] Failed to process ONU", [
                        'olt_id' => $olt->id,
                        'onu_id' => $onuId,
                        'onu_interface' => $onuState['interface'],
                        'error' => $e->getMessage()
                    ]);
                    // Continue with next ONU
                }
            }

            Log::info("[OLT] ONU status update completed", [
                'olt_id' => $olt->id,
                'total_onus' => count($onuStates),
                'processed' => $processedCount,
                'errors' => $errorCount
            ]);

            // Update RX power semua ONU setelah update status
            $this->zteService->updateRxPowerByInterface($olt);
            // Update serial number semua ONU setelah update RX power
            $this->zteService->updateSerialNumberByInterface($olt);

            // Update updated_at OLT agar Last Updated selalu update
            $olt->touch();

            $message = "Status ONU berhasil diperbarui. ";
            if ($errorCount > 0) {
                $message .= "{$errorCount} ONU gagal diproses.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'total_onus' => count($onuStates),
                'processed' => $processedCount,
                'errors' => $errorCount,
                'error_details' => $errors
            ]);
        } catch (\Exception $e) {
            Log::error("[OLT] Failed to update ONU status", [
                'olt_id' => $olt->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = "Gagal memperbarui status ONU: ";
            if (strpos($e->getMessage(), "Gagal terhubung ke OLT") !== false) {
                $errorMessage .= "Tidak dapat terhubung ke OLT. Periksa koneksi jaringan dan kredensial OLT.";
            } elseif (strpos($e->getMessage(), "Gagal menjalankan perintah") !== false) {
                $errorMessage .= "Gagal menjalankan perintah di OLT. Periksa koneksi dan kredensial OLT.";
            } elseif (strpos($e->getMessage(), "Format output tidak sesuai") !== false) {
                $errorMessage .= "Format output tidak sesuai. Pastikan perintah show gpon onu state berjalan dengan benar.";
            } elseif (strpos($e->getMessage(), "Tidak ada data ONU") !== false) {
                $errorMessage .= "Tidak ada data ONU yang ditemukan dalam output.";
            } else {
                $errorMessage .= $e->getMessage();
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage
            ], 500);
        }
    }

    public function configureOnu(Request $request, Onu $onu)
    {
        try {
            $config = $request->validate([
                'vlan' => 'required|integer',
                'tcont' => 'required|integer',
                'gemport' => 'required|integer',
                'service_profile' => 'required|string',
                'type' => 'required|string',
                'description' => 'nullable|string|max:255',
            ]);

            $this->zteService->configureOnu($onu, $config);

            // Simpan nama/description manual ke database jika ada
            if (!empty($config['description'])) {
                $onu->description = $config['description'];
                $onu->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'ONU configured successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure ONU: ' . $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        return view('olt.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        $olt = OltDevice::create($validated);

        return redirect()->route('olt.show', $olt)
            ->with('success', 'OLT device created successfully.');
    }

    public function edit(OltDevice $olt)
    {
        return view('olt.edit', compact('olt'));
    }

    public function update(Request $request, OltDevice $olt)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);
        $olt->update($validated);
        return redirect()->route('olt.index')->with('success', 'OLT updated successfully.');
    }

    public function destroy(OltDevice $olt)
    {
        $olt->delete();
        return redirect()->route('olt.index')->with('success', 'OLT deleted successfully.');
    }
} 