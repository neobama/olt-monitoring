@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Detail ONU: {{ $onu->interface }}</h5>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Serial Number:</strong> {{ $detailInfo['serial_number'] ?? '-' }}<br>
                    <strong>ONU Distance:</strong> {{ $detailInfo['distance'] ?? '-' }}<br>
                    <strong>Online Duration:</strong> {{ $detailInfo['online_duration'] ?? '-' }}<br>
                </div>
                <div class="col-md-6">
                    <strong>Description:</strong> {{ $config['description'] ?? '-' }}
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <h6 class="text-primary">TCONT</h6>
                    @if(!empty($config['tconts']))
                        <ul class="list-group list-group-flush">
                        @foreach($config['tconts'] as $tcont)
                            <li class="list-group-item">{{ $tcont }}</li>
                        @endforeach
                        </ul>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </div>
                <div class="col-md-4">
                    <h6 class="text-primary">GEMPORT</h6>
                    @if(!empty($config['gemports']))
                        <ul class="list-group list-group-flush">
                        @foreach($config['gemports'] as $gemport)
                            <li class="list-group-item">{{ $gemport }}</li>
                        @endforeach
                        </ul>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </div>
                <div class="col-md-4">
                    <h6 class="text-primary">Service Ports (VLAN)</h6>
                    @if(!empty($config['service_ports']))
                        <ul class="list-group list-group-flush">
                        @foreach($config['service_ports'] as $sp)
                            <li class="list-group-item">{{ $sp }}</li>
                        @endforeach
                        </ul>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 