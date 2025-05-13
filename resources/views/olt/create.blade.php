@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Add New OLT</h5>
            <a href="{{ route('olt.index') }}" class="btn btn-outline-secondary">Back to List</a>
        </div>
        <div class="card-body">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('olt.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">OLT Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="e.g. OLT-1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">IP Address</label>
                        <input type="text" name="ip_address" class="form-control" value="{{ old('ip_address') }}" required placeholder="e.g. 192.168.1.1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="{{ old('username') }}" required placeholder="e.g. admin">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="Password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Port</label>
                        <input type="number" name="port" class="form-control" value="{{ old('port', 23) }}" required>
                    </div>
                    <div class="col-md-6 d-flex align-items-center pt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Add OLT</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection 