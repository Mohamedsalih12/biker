@extends('layouts.xpress')
@section('title', 'Warehouse Bin')
@section('subTitle', '')
@section('searchBox', '')

@section('content')
<div class="container-fluid">
    <div class="topbar">
        <div>
            <h3 class="mb-0">Warehouse Bin</h3>
            <div class="small-muted">Deleted warehouses are kept here until restored.</div>
        </div>
        <div class="btn-group-top">
            <a href="{{ route('xpress.warehouse.index') }}" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left" aria-hidden="true"></i>
                Back to Warehouses
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <x-session-message />

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Warehouse</th>
                            <th scope="col">Code</th>
                            <th scope="col">Deleted</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deletedWarehouses as $deletedWarehouse)
                            <tr>
                                <td>{{ $deletedWarehouse->value('warehouse_name') ?? '—' }}</td>
                                <td>{{ $deletedWarehouse->value('warehouse_code') ?? '—' }}</td>
                                <td>{{ $deletedWarehouse->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="text-end">
                                    @can('delete', \App\Models\Xpress\Warehouse::class)
                                        <form method="POST" action="{{ route('xpress.warehouse.restore', $deletedWarehouse->getKey()) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fa fa-undo" aria-hidden="true"></i>
                                                Restore
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4">The Warehouse Bin is empty.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $deletedWarehouses->links() }}
        </div>
    </div>
</div>
@endsection
