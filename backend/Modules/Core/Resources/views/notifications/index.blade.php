@extends('core::layouts.master')

@section('title', 'Notificaciones')

@section('page-title', 'Mis Notificaciones')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Listado de Notificaciones</h5>
        <div>
            <a href="{{ route('core.notifications.unread') }}" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-bell"></i> Ver No Leídas
            </a>
            @if(auth()->user()->canViewModule('notifications'))
            <a href="{{ route('core.notifications.templates') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-list"></i> Plantillas
            </a>
            @endif

            @if(auth()->user()->canCreateInModule('notifications'))
            <a href="{{ route('core.notifications.templates.create') }}" class="btn btn-sm btn-primary">
                <i class="fa fa-plus"></i> Nueva Plantilla
            </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        @if($notifications->isEmpty())
            <div class="alert alert-info mb-0">
                No tienes notificaciones.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Canal</th>
                            <th>Contenido</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notifications as $notification)
                            <tr @if(!isset($notification->metadata['read']) || !$notification->metadata['read']) class="table-primary" @endif>
                                <td>{{ $notification->type }}</td>
                                <td>
                                    @if($notification->channel == 'email')
                                        <span class="badge bg-info">Email</span>
                                    @elseif($notification->channel == 'sms')
                                        <span class="badge bg-success">SMS</span>
                                    @else
                                        <span class="badge bg-secondary">Sistema</span>
                                    @endif
                                </td>
                                <td class="text-truncate" style="max-width: 300px;">
                                    @if(isset($notification->metadata['subject']))
                                        <strong>{{ $notification->metadata['subject'] }}</strong><br>
                                    @endif
                                    {{ $notification->content }}
                                </td>
                                <td>{{ $notification->send_date ? $notification->send_date->format('d/m/Y H:i') : 'Pendiente' }}</td>
                                <td>
                                    @if($notification->status == 'sent')
                                        <span class="badge bg-success">Enviada</span>
                                    @elseif($notification->status == 'failed')
                                        <span class="badge bg-danger">Fallida</span>
                                    @elseif($notification->status == 'queued')
                                        <span class="badge bg-warning">En Cola</span>
                                    @else
                                        <span class="badge bg-secondary">Pendiente</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!isset($notification->metadata['read']) || !$notification->metadata['read'])
                                        <form action="{{ route('core.notifications.mark-as-read', $notification->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-check"></i> Marcar como leída
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted"><i class="fa fa-check"></i> Leída</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
