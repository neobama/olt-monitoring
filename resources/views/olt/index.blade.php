@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">OLT Devices</h5>
            <a href="{{ route('olt.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add OLT
            </a>
        </div>
        <div class="card-body">
            @if($olts->isEmpty())
                <div class="text-center py-4">
                    <i class="fas fa-network-wired fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No OLT devices found. Click the button above to add one.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($olts as $olt)
                                <tr>
                                    <td>{{ $olt->name }}</td>
                                    <td>{{ $olt->ip_address }}</td>
                                    <td>
                                        @if($olt->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $olt->updated_at->diffForHumans() }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('olt.show', $olt) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('olt.edit', $olt) }}" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('olt.destroy', $olt) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this OLT?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection 