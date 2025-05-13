@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Edit OLT</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('olt.update', $olt) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $olt->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">IP Address</label>
                    <input type="text" name="ip_address" class="form-control" value="{{ old('ip_address', $olt->ip_address) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Port</label>
                    <input type="number" name="port" class="form-control" value="{{ old('port', $olt->port) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username', $olt->username) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="text" name="password" class="form-control" value="{{ old('password', $olt->password) }}" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $olt->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="{{ route('olt.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection 