@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">{{ $olt->name }}</h5>
                <small class="text-muted">{{ $olt->ip_address }}</small>
            </div>
            <button type="button" class="btn btn-primary" id="refreshStatusBtn" onclick="refreshStatus()">
                <i class="fas fa-sync-alt"></i> Refresh Status
            </button>
        </div>
        <div class="card-body">
            <div id="statusMessage" class="alert d-none mb-4"></div>


            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Total ONUs</h6>
                            <h2 class="card-title mb-0">{{ $olt->onus->count() }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Online ONUs</h6>
                            <h2 class="card-title mb-0">{{ $olt->onus->where('is_online', true)->count() }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Status</h6>
                            <h2 class="card-title mb-0">
                                @if($olt->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Last Updated</h6>
                            <h2 class="card-title mb-0">{{ $olt->updated_at->diffForHumans() }}</h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">ONU List</h5>
                </div>
                <div class="card-body">
                    @if($olt->onus->isEmpty())
                        <div class="alert alert-info">
                            No ONUs found for this OLT. Please refresh the status to update the ONU list.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Interface</th>
                                        <th>Serial Number</th>
                                        <th>Description</th>
                                        <th>Admin State</th>
                                        <th>OMCC State</th>
                                        <th>Phase State</th>
                                        <th>Rx Power (dBm)</th>
                                        <th>Status</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($olt->onus as $onu)
                                        <tr>
                                            <td>{{ $onu->id }}</td>
                                            <td>{{ $onu->interface }}</td>
                                            <td>{{ $onu->serial_number ?? 'N/A' }}</td>
                                            <td class="onu-description" data-onu-id="{{ $onu->id }}">{{ $onu->description ?? '-' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $onu->admin_state === 'enable' ? 'success' : 'danger' }}">
                                                    {{ $onu->admin_state }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $onu->omcc_state === 'enable' ? 'success' : 'danger' }}">
                                                    {{ $onu->omcc_state }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $onu->phase_state === 'working' ? 'success' : 'danger' }}">
                                                    {{ $onu->phase_state }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($onu->rx_power === null)
                                                    -
                                                @else
                                                    @php
                                                        $rx = $onu->rx_power;
                                                        if ($rx > -24) {
                                                            $color = 'success';
                                                        } elseif ($rx <= -24 && $rx >= -26) {
                                                            $color = 'warning';
                                                        } else {
                                                            $color = 'danger';
                                                        }
                                                    @endphp
                                                    <span class="badge bg-{{ $color }}">{{ $rx }} dBm</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $onu->is_online ? 'success' : 'danger' }}">
                                                    {{ $onu->is_online ? 'Online' : 'Offline' }}
                                                </span>
                                            </td>
                                            <td>{{ $onu->last_seen ? (\Carbon\Carbon::parse($onu->last_seen)->diffForHumans()) : 'Never' }}</td>
                                            <td>
                                                <a href="#" class="btn btn-sm btn-info me-1" onclick="showOnuDetail(event, {{ $onu->id }})">
                                                    <i class="fas fa-info-circle me-1"></i>Detail
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center">No ONUs found</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configure ONU Modal -->
<div class="modal fade" id="configureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure ONU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="configureForm">
                    <input type="hidden" id="onu_id" name="onu_id">
                    <div class="mb-3">
                        <label class="form-label">Description (Nama/Label)</label>
                        <input type="text" class="form-control" name="description" maxlength="255" placeholder="Masukkan nama/label ONU" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveConfiguration()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail ONU -->
<div class="modal fade" id="onuDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-network-wired me-2"></i>Detail ONU
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="onuDetailContent">
                <div class="text-center text-muted py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat data ONU...</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function refreshStatus() {
    console.log('Memulai refresh status...');
    const button = document.getElementById('refreshStatusBtn');
    const statusMessage = document.getElementById('statusMessage');
    
    // Disable button and show loading state
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    statusMessage.className = 'alert alert-info mb-4';
    statusMessage.innerHTML = '<i class="fas fa-info-circle"></i> Refreshing ONU Status...';
    
    // Get CSRF token from meta tag
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    console.log('CSRF Token:', token ? 'Found' : 'Not found');
    
    if (!token) {
        statusMessage.className = 'alert alert-danger mb-4';
        statusMessage.innerHTML = '<i class="fas fa-times-circle"></i> CSRF token tidak ditemukan. Silakan refresh halaman.';
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Status';
        return;
    }

    // Check if user is authenticated
    const isAuthenticated = document.querySelector('meta[name="auth-check"]')?.getAttribute('content') === 'true';
    console.log('Is Authenticated:', isAuthenticated);
    
    if (!isAuthenticated) {
        statusMessage.className = 'alert alert-danger mb-4';
        statusMessage.innerHTML = '<i class="fas fa-times-circle"></i> Sesi Anda telah berakhir. Silakan login kembali.';
        setTimeout(function() {
            window.location.href = '{{ route("login") }}';
        }, 2000);
        return;
    }
    
    // Make AJAX request
    const url = '{{ route("olt.update-status", $olt) }}';
    console.log('Mengirim request ke:', url);
    
    $.ajax({
        url: url,
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
        },
        xhrFields: {
            withCredentials: true
        },
        beforeSend: function(xhr) {
            console.log('Request headers:', xhr.getAllResponseHeaders());
        },
        success: function(response) {
            console.log('Response sukses:', response);
            if (response.success) {
                statusMessage.className = 'alert alert-success mb-4';
                statusMessage.innerHTML = `<i class="fas fa-check-circle"></i> ${response.message}
                    <br><small>Total ONU: ${response.total_onus}</small>`;
                // Reload halaman setelah 2 detik
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                statusMessage.className = 'alert alert-warning mb-4';
                statusMessage.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${response.message}`;
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Status';
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', {
                status: status,
                error: error,
                response: xhr.responseText,
                headers: xhr.getAllResponseHeaders()
            });
            
            let errorMessage = 'Terjadi kesalahan saat memperbarui status';
            
            if (xhr.status === 401) {
                errorMessage = 'Sesi Anda telah berakhir. Silakan login kembali.';
                // Redirect to login page after 2 seconds
                setTimeout(function() {
                    window.location.href = '{{ route("login") }}';
                }, 2000);
            } else if (xhr.status === 419) {
                errorMessage = 'CSRF token tidak valid. Silakan refresh halaman.';
                // Refresh page after 2 seconds
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                }
            }
            
            statusMessage.className = 'alert alert-danger mb-4';
            statusMessage.innerHTML = `<i class="fas fa-times-circle"></i> ${errorMessage}`;
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh Status';
        }
    });
}

function configureOnu(onuId) {
    // Set ONU ID in form
    document.getElementById('onu_id').value = onuId;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('configureModal')).show();
}

function saveConfiguration() {
    const form = document.getElementById('configureForm');
    const formData = new FormData(form);
    const onuId = formData.get('onu_id');
    
    // Make AJAX call to save configuration
    $.ajax({
        url: `/onu/${onuId}/configure`,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        success: function(response) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('configureModal')).hide();
            
            // Show success message
            const statusMessage = document.getElementById('statusMessage');
            statusMessage.className = 'alert alert-success';
            statusMessage.textContent = response.message;
            statusMessage.classList.remove('d-none');
            
            // Reload page after 2 seconds
            setTimeout(function() {
                window.location.reload();
            }, 2000);
        },
        error: function(xhr) {
            // Show error message
            const statusMessage = document.getElementById('statusMessage');
            statusMessage.className = 'alert alert-danger';
            statusMessage.textContent = xhr.responseJSON?.message || 'An error occurred while saving configuration.';
            statusMessage.classList.remove('d-none');
        }
    });
}

function showOnuDetail(event, onuId) {
    if (event) event.preventDefault();
    const modal = new bootstrap.Modal(document.getElementById('onuDetailModal'));
    const content = document.getElementById('onuDetailContent');
    content.innerHTML = `
        <div class="text-center text-muted py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Memuat data ONU...</p>
        </div>`;
    modal.show();
    
    // Update description di database sebelum fetch detail
    fetch(`/onu/${onuId}/update-description`, { 
        method: 'POST', 
        headers: { 
            'X-CSRF-TOKEN': '{{ csrf_token() }}', 
            'Accept': 'application/json' 
        } 
    })
    .then(res => res.json())
    .then(descData => {
        if (descData.description) {
            const descCell = document.querySelector(`.onu-description[data-onu-id='${onuId}']`);
            if (descCell) descCell.textContent = descData.description;
        }
        return fetch(`/onu/${onuId}/detail`, { 
            headers: { 'Accept': 'application/json' } 
        });
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            content.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="fas fa-exclamation-circle me-2"></i>${data.error}
                </div>`;
            return;
        }

        // Pastikan data ada sebelum membuat HTML
        const detailInfo = data.detailInfo || {};
        const config = data.config || {};
        const wanInfo = data.wanInfo || {};
        const equipInfo = data.equipInfo || {};

        console.log('ONU DetailInfo:', detailInfo);

        let html = `
        <div class="container-fluid p-3">
            <!-- Status Badge -->
            <div class="d-flex justify-content-end mb-3">
                <span class="badge bg-${data.onu.is_online ? 'success' : 'danger'} p-2">
                    <i class="fas fa-circle me-1"></i>${data.onu.is_online ? 'Online' : 'Offline'}
                </span>
            </div>

            <!-- Main Info Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light d-flex align-items-center">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <h6 class="mb-0">Basic Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-fingerprint text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Serial Number</small>
                                            <strong>${detailInfo.serial_number || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-ruler text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">ONU Distance</small>
                                            <strong>${detailInfo.distance || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-clock text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Online Duration</small>
                                            <strong>${detailInfo.online_duration || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-tag text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Description</small>
                                            <strong>${config.description || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-microchip text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">ONU Model</small>
                                            <strong>${equipInfo.model || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light d-flex align-items-center">
                            <i class="fas fa-network-wired text-primary me-2"></i>
                            <h6 class="mb-0">WAN Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-globe text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">IP Address</small>
                                            <strong>${wanInfo.ip || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-mask text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Subnet Mask</small>
                                            <strong>${wanInfo.mask || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-route text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Gateway</small>
                                            <strong>${wanInfo.gateway || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-server text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Primary DNS</small>
                                            <strong>${wanInfo.dns1 || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-server text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Secondary DNS</small>
                                            <strong>${wanInfo.dns2 || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Username</small>
                                            <strong>${wanInfo.username || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-key text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">Password</small>
                                            <strong>${wanInfo.password || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-ethernet text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">MAC Address</small>
                                            <strong>${wanInfo.mac || '-'}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration Cards -->
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light d-flex align-items-center">
                            <i class="fas fa-cogs text-primary me-2"></i>
                            <h6 class="mb-0">TCONT</h6>
                        </div>
                        <div class="card-body">
                            ${config.tconts && config.tconts.length ? 
                                `<ul class="list-group list-group-flush">
                                    ${config.tconts.map(t => `
                                        <li class="list-group-item d-flex align-items-center">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            ${t}
                                        </li>
                                    `).join('')}
                                </ul>` : 
                                '<div class="text-center text-muted py-3"><i class="fas fa-info-circle me-2"></i>No TCONT configuration</div>'
                            }
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light d-flex align-items-center">
                            <i class="fas fa-plug text-primary me-2"></i>
                            <h6 class="mb-0">GEMPORT</h6>
                        </div>
                        <div class="card-body">
                            ${config.gemports && config.gemports.length ? 
                                `<ul class="list-group list-group-flush">
                                    ${config.gemports.map(t => `
                                        <li class="list-group-item d-flex align-items-center">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            ${t}
                                        </li>
                                    `).join('')}
                                </ul>` : 
                                '<div class="text-center text-muted py-3"><i class="fas fa-info-circle me-2"></i>No GEMPORT configuration</div>'
                            }
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-light d-flex align-items-center">
                            <i class="fas fa-tags text-primary me-2"></i>
                            <h6 class="mb-0">Service Ports (VLAN)</h6>
                        </div>
                        <div class="card-body">
                            ${config.service_ports && config.service_ports.length ? 
                                `<ul class="list-group list-group-flush">
                                    ${config.service_ports.map(t => `
                                        <li class="list-group-item d-flex align-items-center">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            ${t}
                                        </li>
                                    `).join('')}
                                </ul>` : 
                                '<div class="text-center text-muted py-3"><i class="fas fa-info-circle me-2"></i>No Service Ports configuration</div>'
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
        
        content.innerHTML = html;
    })
    .catch(err => {
        console.error('Error fetching ONU details:', err);
        content.innerHTML = `
            <div class="alert alert-danger m-3">
                <i class="fas fa-exclamation-circle me-2"></i>Gagal mengambil detail ONU: ${err.message}
            </div>`;
    });
}
</script>
@endpush
@endsection 