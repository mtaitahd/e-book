@extends('layouts.admin.app')

@section('title', 'Subscribers')
@section('heading', 'Subscribers')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div class="text-muted small">
                {{ $subscribers->total() }} {{ $status === null ? 'subscriber' : 'matching subscriber' }}{{ $subscribers->total() === 1 ? '' : 's' }} shown
            </div>

            {{-- Opening the Subscribe popup. --}}
            <button type="button" class="btn btn-primary btn-icon-split" id="openSubscribeModal"
                    data-toggle="modal" data-target="#subscribeModal">
                <span class="icon text-white-50"><i class="fas fa-envelope"></i></span>
                <span class="text">Subscribe</span>
            </button>
        </div>

        <div class="btn-group btn-group-sm mb-3" role="group" aria-label="Filter subscribers by status">
            @foreach($filters as $filter)
                <a class="btn {{ $status === $filter['value'] ? 'btn-primary' : 'btn-outline-primary' }}"
                   href="{{ route('admin.subscribers.index', $filter['value'] ? ['status' => $filter['value']] : []) }}">
                    {{ $filter['label'] }}
                    <span class="badge badge-light ml-1">{{ $filter['count'] }}</span>
                </a>
            @endforeach
        </div>

        @if($subscribers->isEmpty())
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <i class="fas fa-envelope-open-text fa-3x text-gray-300 mb-3"></i>

                    @if($status === null)
                        <p class="mb-1 text-gray-800">No subscribers yet.</p>
                        <p class="small text-muted mb-3">
                            Add an address and it will be kept for new-book and offer announcements.
                        </p>
                    @else
                        <p class="mb-1 text-gray-800">
                            No {{ $status === \App\Models\Subscriber::STATUS_ACTIVE ? 'active' : 'unsubscribed' }} subscribers.
                        </p>
                        <p class="small text-muted mb-3">
                            <a href="{{ route('admin.subscribers.index') }}">Show all subscribers</a>
                        </p>
                    @endif

                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#subscribeModal">
                        <i class="fas fa-plus mr-1"></i> Subscribe
                    </button>
                </div>
            </div>
        @else
            <div class="card shadow mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Email</th>
                                    <th scope="col" style="width: 140px;">Status</th>
                                    <th scope="col" style="width: 170px;">Subscribed</th>
                                    <th scope="col" style="width: 200px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subscribers as $subscriber)
                                    <tr>
                                        <td class="text-break">{{ $subscriber->email }}</td>
                                        <td>
                                            @if($subscriber->isActive())
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-secondary">Unsubscribed</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">
                                            {{ $subscriber->subscribed_at?->format('j M Y') ?? '—' }}
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.subscribers.status', $subscriber) }}"
                                                  class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm {{ $subscriber->isActive() ? 'btn-outline-secondary' : 'btn-primary' }}"
                                                        title="{{ $subscriber->isActive() ? 'Mark as unsubscribed' : 'Resubscribe' }}">
                                                    @if($subscriber->isActive())
                                                        <i class="fas fa-user-slash"></i> Unsubscribe
                                                    @else
                                                        <i class="fas fa-user-check"></i> Resubscribe
                                                    @endif
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.subscribers.destroy', $subscriber) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Remove {{ $subscriber->email }} from the list permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" title="Remove">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    {{ $subscribers->links() }}
                </div>
            </div>
        @endif
    </div>

    {{--
        The Subscribe popup. Kept as a plain POST form rather than an injected
        one so the address is submitted, validated and reported without any
        JavaScript involvement beyond Bootstrap opening the dialog.
    --}}
    <div class="modal fade" id="subscribeModal" tabindex="-1" role="dialog" aria-labelledby="subscribeModalTitle"
         aria-hidden="true" data-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.subscribers.store') }}" novalidate>
                    @csrf

                    {{-- Marks which form failed so the popup reopens on itself. --}}
                    <input type="hidden" name="_form" value="subscribe">

                    <div class="modal-header py-3">
                        <h5 class="modal-title h6 m-0 font-weight-bold" id="subscribeModalTitle">Subscribe an email address</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <p class="small text-muted">
                            Add an address to the newsletter list. It will receive new-book and offer announcements.
                        </p>

                        <div class="form-group mb-0">
                            <label for="subscribeEmail">Email address <span class="text-danger">*</span></label>
                            <input type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   id="subscribeEmail"
                                   name="email"
                                   value="{{ old('email') }}"
                                   inputmode="email"
                                   autocomplete="off"
                                   placeholder="reader@example.com"
                                   required>
                            @error('email')
                                <span class="invalid-feedback d-block">{{ $message }}</span>
                            @enderror
                            <small class="form-text text-muted">
                                An address can only be on the list once, whatever its capitalisation.
                            </small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane mr-1"></i> Subscribe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{--
        A failed subscription redirects back to this page, which would otherwise
        close the popup and leave the administrator looking at a bare list. Reopen
        it so the message lands next to the field that caused it. The layout's
        own error alert is hidden in that case, because the same message is shown
        inline in the popup.
    --}}
    @if($errors->any() && old('_form') === 'subscribe')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                $('.alert-danger').hide();
                $('#subscribeModal').modal('show');
            });
        </script>
    @endif
@endpush
