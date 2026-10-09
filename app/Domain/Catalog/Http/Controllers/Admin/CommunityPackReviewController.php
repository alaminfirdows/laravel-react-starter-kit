<?php

namespace App\Domain\Catalog\Http\Controllers\Admin;

use App\Domain\Activity\Data\Actor;
use App\Domain\Catalog\Actions\ReviewCommunityPack;
use App\Domain\Catalog\Enums\PackReviewStatus;
use App\Domain\Catalog\Http\Requests\ReviewCommunityPackRequest;
use App\Domain\Catalog\Http\Resources\CommunityPackResource;
use App\Domain\Catalog\Models\Pack;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CommunityPackReviewController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/community-packs/index', [
            'packs' => CommunityPackResource::collection(Pack::query()
                ->where('review_status', PackReviewStatus::Pending)
                ->with(['ownerWorkspace:id,name', 'items.catalogTask:id,key,title'])
                ->oldest('updated_at')
                ->get()),
        ]);
    }

    public function store(ReviewCommunityPackRequest $request, Pack $pack, ReviewCommunityPack $review): RedirectResponse
    {
        try {
            $review->handle($pack, $request->decision(), $request->input('note'), Actor::user($request->user()));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['decision' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review saved.')]);

        return back();
    }
}
