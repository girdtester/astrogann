<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\AnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Super Admin → Announcements (D-100): write a message that appears in users' Notifications on web + app.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(): View
    {
        return view('superadmin.announcements.index', [
            'announcements' => Announcement::query()
                ->with('author:id,name')
                ->withCount(['notifications as read_count' => fn ($q) => $q->where('is_read', true)])
                ->latest('id')
                ->paginate(20),
            'audiences' => Announcement::AUDIENCES,
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $this->announcements->send($request->validated(), $request->user());

        return redirect()->route('superadmin.announcements.index')
            ->with('success', 'Announcement sent. Users see it in Notifications within a few seconds (large user lists finish over the next minutes).');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->announcements->delete($announcement, request()->user());

        return back()->with('success', 'Announcement deleted and removed from users\' Notifications.');
    }
}
