<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriberRequest;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriberController extends Controller
{
    /**
     * The only two statuses a subscriber can be filtered by.
     *
     * @var list<string>
     */
    private const STATUSES = [
        Subscriber::STATUS_ACTIVE,
        Subscriber::STATUS_UNSUBSCRIBED,
    ];

    /**
     * List subscribers, optionally narrowed to one status.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = in_array($status, self::STATUSES, true) ? $status : null;

        $subscribers = Subscriber::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.subscribers.index', [
            'subscribers' => $subscribers,
            'status' => $status,
            'filters' => [
                ['label' => 'All', 'value' => null, 'count' => Subscriber::count()],
                ['label' => 'Active', 'value' => Subscriber::STATUS_ACTIVE, 'count' => Subscriber::active()->count()],
                ['label' => 'Unsubscribed', 'value' => Subscriber::STATUS_UNSUBSCRIBED, 'count' => Subscriber::unsubscribed()->count()],
            ],
        ]);
    }

    /**
     * Add an address to the mailing list.
     */
    public function store(SubscriberRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        Subscriber::create([
            'email' => $email,
            'status' => Subscriber::STATUS_ACTIVE,
        ]);

        return redirect()
            ->route('admin.subscribers.index')
            ->with('success', $email.' has been added to the list.');
    }

    /**
     * Toggle a subscriber between active and unsubscribed.
     *
     * Unsubscribing is a status change rather than a delete so the join date
     * and the record of the address are kept, which is what an unsubscribe
     * request is expected to preserve.
     */
    public function updateStatus(Subscriber $subscriber): RedirectResponse
    {
        $email = $subscriber->email;

        $subscriber->update([
            'status' => $subscriber->isActive()
                ? Subscriber::STATUS_UNSUBSCRIBED
                : Subscriber::STATUS_ACTIVE,
        ]);

        $message = $subscriber->isActive()
            ? $email.' has been resubscribed.'
            : $email.' has been unsubscribed.';

        return redirect()
            ->route('admin.subscribers.index')
            ->with('success', $message);
    }

    /**
     * Remove an address from the list entirely.
     */
    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $email = $subscriber->email;

        $subscriber->delete();

        return redirect()
            ->route('admin.subscribers.index')
            ->with('success', $email.' has been removed from the list.');
    }
}
